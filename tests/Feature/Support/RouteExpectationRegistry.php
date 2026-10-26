<?php

namespace Tests\Feature\Support;

use Database\Seeders\E2eFixtureSeeder;

/**
 * Core GET journeys must use stable, linked fixture IDs and have an explicit
 * status expectation. Routes outside this short critical-path list are still
 * visited by RouteSweepTest's broad GET/no-5xx sweep.
 */
final class RouteExpectationRegistry
{
    /** @return array<int, array{route: string, params: array<string, int|string>, role: string, expected: int, label: string}> */
    public static function core(): array
    {
        return [
            [
                'route' => 'cbt.student.index',
                'params' => [],
                'role' => 'siswa_a1',
                'expected' => 200,
                'label' => 'CBT student session list',
            ],
            [
                'route' => 'cbt.student.start',
                'params' => ['session' => E2eFixtureSeeder::CBT_SESSION_ID],
                'role' => 'siswa_a1',
                'expected' => 200,
                'label' => 'CBT session instructions before attempt creation',
            ],
            [
                'route' => 'cbt.student.result',
                'params' => ['result' => E2eFixtureSeeder::CBT_RESULT_ID],
                'role' => 'siswa_a1',
                'expected' => 200,
                'label' => 'CBT result owner access',
            ],
            [
                'route' => 'cbt.student.result',
                'params' => ['result' => E2eFixtureSeeder::CBT_RESULT_ID],
                'role' => 'siswa_a2',
                'expected' => 403,
                'label' => 'CBT result non-owner denial',
            ],
            [
                'route' => 'admin.cbt.sessions.show',
                'params' => ['session' => E2eFixtureSeeder::CBT_SESSION_ID],
                'role' => 'admin',
                'expected' => 200,
                'label' => 'Admin CBT session detail',
            ],
            [
                'route' => 'admin.cbt.sessions.export',
                'params' => ['session' => E2eFixtureSeeder::CBT_SESSION_ID, 'format' => 'csv'],
                'role' => 'admin',
                'expected' => 200,
                'label' => 'Admin CBT CSV export',
            ],
            [
                'route' => 'tryout.sessions.show',
                'params' => ['session' => E2eFixtureSeeder::TRYOUT_SESSION_ID],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'Tryout session owner detail',
            ],
            [
                'route' => 'admin.tryout.sessions.show',
                'params' => ['session' => E2eFixtureSeeder::TRYOUT_SESSION_ID],
                'role' => 'admin',
                'expected' => 200,
                'label' => 'Admin Tryout session detail',
            ],
            [
                'route' => 'tryout.student.index',
                'params' => [],
                'role' => 'siswa_a1',
                'expected' => 200,
                'label' => 'Tryout student list and history',
            ],
            [
                'route' => 'tryout.student.start',
                'params' => ['session' => E2eFixtureSeeder::TRYOUT_SESSION_ID],
                'role' => 'siswa_a1',
                'expected' => 200,
                'label' => 'Tryout take page',
            ],
            [
                'route' => 'tryout.student.result',
                'params' => ['result' => E2eFixtureSeeder::TRYOUT_RESULT_ID],
                'role' => 'siswa_a1',
                'expected' => 200,
                'label' => 'Tryout result owner access',
            ],
            [
                'route' => 'tryout.student.result',
                'params' => ['result' => E2eFixtureSeeder::TRYOUT_RESULT_ID],
                'role' => 'siswa_a2',
                'expected' => 403,
                'label' => 'Tryout result non-owner denial',
            ],
            [
                'route' => 'admin.tryout.results.show',
                'params' => ['result' => E2eFixtureSeeder::TRYOUT_RESULT_ID],
                'role' => 'admin',
                'expected' => 200,
                'label' => 'Admin Tryout result detail',
            ],
            [
                'route' => 'tka.assessments.index',
                'params' => [],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'TKA assessment teacher list',
            ],
            [
                'route' => 'tka.assessments.show',
                'params' => ['assessment' => E2eFixtureSeeder::TKA_ASSESSMENT_A_ID],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'TKA assessment owner detail',
            ],
            [
                'route' => 'tka.assessments.show',
                'params' => ['assessment' => E2eFixtureSeeder::TKA_ASSESSMENT_B_ID],
                'role' => 'guru_a',
                'expected' => 403,
                'label' => 'TKA assessment other-teacher denial',
            ],
            [
                'route' => 'tka.assessments.by-siswa',
                'params' => ['siswaId' => E2eFixtureSeeder::STUDENT_IDS['siswa_a1']],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'TKA student assessment history',
            ],
            [
                'route' => 'tka.dimensions.show',
                'params' => ['dimension' => E2eFixtureSeeder::TKA_DIMENSION_ID],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'TKA dimension and nested items',
            ],
            [
                'route' => 'tka.dimensions.api.items',
                'params' => ['dimension' => E2eFixtureSeeder::TKA_DIMENSION_ID],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'TKA dimension items API',
            ],
            [
                'route' => 'tka.parent.show',
                'params' => ['report' => E2eFixtureSeeder::TKA_PARENT_REPORT_ID],
                'role' => 'ortu_a1',
                'expected' => 200,
                'label' => 'TKA parent report linked-child access',
            ],
            [
                'route' => 'tka.parent.show',
                'params' => ['report' => E2eFixtureSeeder::TKA_PARENT_REPORT_ID],
                'role' => 'ortu_b1',
                'expected' => 403,
                'label' => 'TKA parent report unrelated-child denial',
            ],
            [
                'route' => 'guru.laporan.index',
                'params' => [],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'Teacher report without teacher settings',
            ],
            [
                'route' => 'guru.laporan.pdf',
                'params' => [],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'Teacher PDF report without teacher settings',
            ],
            [
                'route' => 'guru.erapor.excel',
                'params' => [],
                'role' => 'guru_a',
                'expected' => 200,
                'label' => 'e-Rapor export without teacher settings',
            ],
            [
                'route' => 'ortu.dashboard',
                'params' => [],
                'role' => 'ortu_a1',
                'expected' => 200,
                'label' => 'Parent dashboard with linked child',
            ],
        ];
    }

    /**
     * Return stable fixture values for named parameters; unknown route
     * parameters fall back to `1` in the broad sweep and are not core routes.
     *
     * @param array<int, string> $parameterNames
     * @return array<string, int|string>
     */
    public static function parametersFor(?string $routeName, array $parameterNames): array
    {
        $known = [
            'bank' => E2eFixtureSeeder::CBT_BANK_ID,
            'session' => E2eFixtureSeeder::CBT_SESSION_ID,
            'result' => E2eFixtureSeeder::CBT_RESULT_ID,
            'question' => 9302,
            'siswa' => E2eFixtureSeeder::STUDENT_IDS['siswa_a1'],
            'siswaId' => E2eFixtureSeeder::STUDENT_IDS['siswa_a1'],
            'assessment' => E2eFixtureSeeder::TKA_ASSESSMENT_A_ID,
            'report' => E2eFixtureSeeder::TKA_PARENT_REPORT_ID,
            'dimension' => E2eFixtureSeeder::TKA_DIMENSION_ID,
            'item' => E2eFixtureSeeder::TKA_ITEM_LIGHT_ID,
            'package' => E2eFixtureSeeder::TRYOUT_PACKAGE_ID,
            'category' => E2eFixtureSeeder::TRYOUT_CATEGORY_ID,
            'subject' => E2eFixtureSeeder::TRYOUT_SUBJECT_ID,
            'warning' => 1,
            'format' => 'csv',
            'id' => E2eFixtureSeeder::STUDENT_IDS['siswa_a1'],
        ];

        foreach (self::core() as $case) {
            if ($case['route'] === $routeName) {
                $known = array_merge($known, $case['params']);
                break;
            }
        }

        $params = [];
        foreach ($parameterNames as $parameterName) {
            $params[$parameterName] = $known[$parameterName] ?? '1';
        }

        return $params;
    }
}
