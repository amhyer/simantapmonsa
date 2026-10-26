<?php

namespace Tests\Feature;

use App\Models\CbtResult;
use App\Models\User;
use App\Models\Tryout\Result as TryoutResult;
use App\Models\Tryout\StudentAnswer;
use App\Models\Tka\AssessmentItem;
use App\Models\Tka\ParentReport;
use App\Models\Exam\Attempt as ExamAttempt;
use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Support\RouteExpectationRegistry;
use Tests\TestCase;

class RouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private array $cbtFlagBeforeBoot = [];

    protected function setUp(): void
    {
        // CBT routes are conditionally registered during application boot.
        $this->cbtFlagBeforeBoot = [
            getenv('SIMANTAP_CBT_ENABLED'),
            $_ENV['SIMANTAP_CBT_ENABLED'] ?? null,
            $_SERVER['SIMANTAP_CBT_ENABLED'] ?? null,
        ];
        putenv('SIMANTAP_CBT_ENABLED=true');
        $_ENV['SIMANTAP_CBT_ENABLED'] = $_SERVER['SIMANTAP_CBT_ENABLED'] = 'true';
        \Illuminate\Support\Env::enablePutenv();

        parent::setUp();

        $this->withoutVite();

        config([
            'simantap.cbt_enabled' => true,
            'simantap.exam_engine_cbt' => true,
            'simantap.exam_engine_tryout' => true,
        ]);

        $this->seed(E2eFixtureSeeder::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        [$env, $arrayEnv, $server] = $this->cbtFlagBeforeBoot;
        $env === false
            ? putenv('SIMANTAP_CBT_ENABLED')
            : putenv('SIMANTAP_CBT_ENABLED='.$env);

        if ($arrayEnv === null) {
            unset($_ENV['SIMANTAP_CBT_ENABLED']);
        } else {
            $_ENV['SIMANTAP_CBT_ENABLED'] = $arrayEnv;
        }

        if ($server === null) {
            unset($_SERVER['SIMANTAP_CBT_ENABLED']);
        } else {
            $_SERVER['SIMANTAP_CBT_ENABLED'] = $server;
        }

        \Illuminate\Support\Env::enablePutenv();
    }

    public function test_core_route_registry_matches_expected_statuses_with_fixture_ids(): void
    {
        foreach (RouteExpectationRegistry::core() as $case) {
            if (in_array($case['route'], ['cbt.student.result', 'admin.cbt.sessions.export'], true)) {
                $this->createCbtResult(submitted: true);
            }

            $user = User::findOrFail(E2eFixtureSeeder::USER_IDS[$case['role']]);
            $url = route($case['route'], $case['params']);
            $response = $this->actingAs($user)->get($url);

            $this->assertSame(
                $case['expected'],
                $response->getStatusCode(),
                "{$case['label']} ({$case['route']} {$url}) returned HTTP {$response->getStatusCode()}"
            );
        }

        $this->assertDatabaseMissing('pengaturan_guru', ['guru_id' => E2eFixtureSeeder::USER_IDS['guru_a']]);
    }

    public function test_broad_get_route_sweep_has_no_5xx_for_all_fixture_roles(): void
    {
        $users = [];
        foreach (E2eFixtureSeeder::ROLE_IDS as $role => $id) {
            $users[$role] = User::findOrFail($id);
        }

        $skipNames = [
            'login', 'logout', 'register',
            'password.request', 'password.email', 'password.reset', 'password.store',
            'verification.notice', 'verification.verify', 'verification.send',
        ];

        $failures = [];
        $tested = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();
            if (str_contains($uri, '_ignition') || str_contains($uri, 'telescope')
                || str_contains($uri, 'sanctum') || str_contains($uri, 'livewire')) {
                continue;
            }

            $name = $route->getName();
            if ($name !== null && in_array($name, $skipNames, true)) {
                continue;
            }

            $path = $this->pathFor($route);
            foreach ($users as $role => $user) {
                if (in_array($name, ['cbt.student.result', 'admin.cbt.sessions.export'], true)) {
                    $this->createCbtResult(submitted: true);
                }

                $tested++;
                try {
                    $status = $this->actingAs($user)->get($path)->getStatusCode();
                } catch (\Throwable $exception) {
                    $failures[] = "{$role} GET {$path} ({$name}) => EXCEPTION: {$exception->getMessage()}";
                    continue;
                }

                if ($status >= 500) {
                    $failures[] = "{$role} GET {$path} ({$name}) => HTTP {$status}";
                }
            }
        }

        $report = $failures === []
            ? "OK: 0 endpoint 5xx dari {$tested} kombinasi route GET x akun fixture."
            : 'Ditemukan '.count($failures)." endpoint 5xx dari {$tested} kombinasi:\n".implode(PHP_EOL, $failures);

        File::ensureDirectoryExists(storage_path('logs'));
        file_put_contents(storage_path('logs/route-health-report.txt'), $report);
        fwrite(STDOUT, $report.PHP_EOL);

        $this->assertGreaterThan(1000, $tested, 'Sweep harus menguji seluruh route GET terhadap akun fixture lintas peran.');
        $this->assertSame([], $failures, "Route GET menghasilkan 5xx:\n".implode("\n", array_slice($failures, 0, 50)));
    }

    public function test_denied_cbt_requests_leave_result_and_integrity_state_unchanged(): void
    {
        $result = $this->createCbtResult(submitted: false);
        $before = $result->fresh()->getAttributes();
        $otherStudent = User::findOrFail(E2eFixtureSeeder::USER_IDS['siswa_a2']);

        $this->actingAs($otherStudent)
            ->get(route('cbt.student.result', $result))
            ->assertForbidden();
        $this->actingAs($otherStudent)
            ->postJson(route('cbt.student.answer', $result), [
                'question_id' => 9302,
                'answer' => 'A',
            ])
            ->assertForbidden();
        $this->actingAs($otherStudent)
            ->postJson(route('cbt.student.submit', $result), ['answers' => [9302 => 'A']])
            ->assertForbidden();
        $this->actingAs($otherStudent)
            ->postJson(route('cbt.student.integrity', $result), ['type' => 'tab_hidden'])
            ->assertForbidden();

        $this->assertSame($before, $result->fresh()->getAttributes(), 'Aktor yang ditolak tidak boleh mengubah hasil CBT.');
        $this->assertDatabaseCount('cbt_violations', 0);
        $this->assertSame(0, ExamAttempt::query()->count(), 'Penolakan CBT tidak boleh membuat exam_attempt.');
    }

    public function test_denied_tryout_requests_do_not_change_attempt_answers_or_completion(): void
    {
        $result = TryoutResult::findOrFail(E2eFixtureSeeder::TRYOUT_RESULT_ID);
        $before = $result->getAttributes();
        $otherStudent = User::findOrFail(E2eFixtureSeeder::USER_IDS['siswa_a2']);
        $unrelatedParent = User::findOrFail(E2eFixtureSeeder::USER_IDS['ortu_b1']);
        $answerCount = StudentAnswer::query()->count();
        $attemptCount = ExamAttempt::query()->count();

        $this->actingAs($otherStudent)
            ->get(route('tryout.student.result', $result))
            ->assertForbidden();
        $this->actingAs($otherStudent)
            ->postJson(route('tryout.student.answer', $result), [
                'subject_id' => E2eFixtureSeeder::TRYOUT_SUBJECT_ID,
                'question_id' => E2eFixtureSeeder::TRYOUT_QUESTION_ID,
                'jawaban' => 'A',
            ])
            ->assertForbidden();
        $this->actingAs($otherStudent)
            ->post(route('tryout.student.complete', $result))
            ->assertForbidden();
        $this->actingAs($unrelatedParent)
            ->get(route('tryout.student.result', $result))
            ->assertForbidden();

        $this->assertSame($before, $result->fresh()->getAttributes(), 'Penolakan Tryout tidak boleh mengubah row percobaan.');
        $this->assertSame($answerCount, StudentAnswer::query()->count());
        $this->assertSame($attemptCount, ExamAttempt::query()->count());
        $this->assertNull($result->fresh()->waktu_selesai);
    }

    public function test_tka_ownership_and_nested_item_denials_do_not_mutate_records(): void
    {
        $teacherA = User::findOrFail(E2eFixtureSeeder::USER_IDS['guru_a']);
        $parentB = User::findOrFail(E2eFixtureSeeder::USER_IDS['ortu_b1']);
        $report = ParentReport::findOrFail(E2eFixtureSeeder::TKA_PARENT_REPORT_ID);
        $reportBefore = $report->getAttributes();
        $otherDimensionItem = AssessmentItem::findOrFail(E2eFixtureSeeder::TKA_OTHER_ITEM_ID);

        $this->actingAs($teacherA)
            ->get(route('tka.assessments.show', E2eFixtureSeeder::TKA_ASSESSMENT_B_ID))
            ->assertForbidden();
        $this->actingAs($teacherA)
            ->delete(route('tka.dimensions.destroy-item', [
                'dimension' => E2eFixtureSeeder::TKA_DIMENSION_ID,
                'item' => E2eFixtureSeeder::TKA_OTHER_ITEM_ID,
            ]))
            ->assertNotFound();
        $this->actingAs($teacherA)
            ->get(route('tka.parent.show', $report))
            ->assertForbidden();
        $this->actingAs($parentB)
            ->get(route('tka.parent.show', $report))
            ->assertForbidden();

        $this->assertSame($reportBefore, $report->fresh()->getAttributes(), 'Akses laporan yang ditolak tidak boleh menandai report sudah dibaca.');
        $this->assertFalse($report->fresh()->dibaca_oleh_ortu);
        $this->assertSame(E2eFixtureSeeder::TKA_OTHER_DIMENSION_ID, (int) $otherDimensionItem->dimension_id);
        $this->assertDatabaseHas('tka_assessment_items', [
            'id' => E2eFixtureSeeder::TKA_OTHER_ITEM_ID,
            'dimension_id' => E2eFixtureSeeder::TKA_OTHER_DIMENSION_ID,
        ]);
    }

    public function test_tryout_resume_restores_saved_answer_for_the_correct_session_student_attempt(): void
    {
        StudentAnswer::query()->create([
            'tryout_session_id' => E2eFixtureSeeder::TRYOUT_SESSION_ID,
            'tryout_subject_id' => E2eFixtureSeeder::TRYOUT_SUBJECT_ID,
            'siswa_id' => E2eFixtureSeeder::STUDENT_IDS['siswa_a1'],
            'question_id' => E2eFixtureSeeder::TRYOUT_QUESTION_ID,
            'jawaban' => 'A',
            'adalah_benar' => true,
            'poin_diperoleh' => 1,
            'waktu_mulai_jawab' => now(),
            'waktu_selesai_jawab' => now(),
            'durasi_detik' => 0,
            'attempt_number' => 1,
        ]);

        $result = TryoutResult::findOrFail(E2eFixtureSeeder::TRYOUT_RESULT_ID);
        $this->assertSame(['A'], $result->answers()->pluck('jawaban')->all());

        $response = $this->actingAs(User::findOrFail(E2eFixtureSeeder::USER_IDS['siswa_a1']))
            ->get(route('tryout.student.start', E2eFixtureSeeder::TRYOUT_SESSION_ID));

        $response->assertOk()
            ->assertSeeInOrder(['name="q_9404"', 'value="A"', 'checked'], false);
    }

    public function test_teacher_tka_index_only_lists_its_assessments(): void
    {
        $response = $this->actingAs(User::findOrFail(E2eFixtureSeeder::USER_IDS['guru_a']))
            ->get(route('tka.assessments.index'));

        $response->assertOk()
            ->assertSee('Siswa E2E A1')
            ->assertDontSee('Siswa E2E B1');
    }

    private function createCbtResult(bool $submitted): CbtResult
    {
        $answers = $submitted
            ? array_fill_keys(range(9302, 9306), 'A')
            : [];

        return CbtResult::query()->updateOrCreate(
            ['id' => E2eFixtureSeeder::CBT_RESULT_ID],
            [
                'session_id' => E2eFixtureSeeder::CBT_SESSION_ID,
                'siswa_id' => E2eFixtureSeeder::STUDENT_IDS['siswa_a1'],
                'user_id' => E2eFixtureSeeder::USER_IDS['siswa_a1'],
                'started_at' => now()->subMinute(),
                'deadline_at' => now()->addMinutes(59),
                'submitted_at' => $submitted ? now() : null,
                'answers' => $answers,
                'correct_count' => $submitted ? 5 : 0,
                'score' => $submitted ? 100 : null,
            ]
        );
    }

    private function pathFor(object $route): string
    {
        $path = '/'.ltrim($route->uri(), '/');
        $parameters = RouteExpectationRegistry::parametersFor($route->getName(), $route->parameterNames());

        foreach ($route->parameterNames() as $parameter) {
            $replacement = rawurlencode((string) ($parameters[$parameter] ?? '1'));
            $pattern = '/\{'.preg_quote($parameter, '/').'\??\}/';
            $path = preg_replace($pattern, $replacement, $path, 1) ?? $path;
        }

        return $path;
    }
}
