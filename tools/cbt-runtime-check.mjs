/** PHP-wasm lint + uji murni CBT. Bukan pengganti Laravel/PHPUnit.
 * Penggunaan: node tools/cbt-runtime-check.mjs /path/to/php-wasm/PhpNode.mjs
 * Paket dipasang DI LUAR repository, mis. npm install --prefix /tmp/phpwasm php-wasm@0.2.0
 */
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

if (!process.argv[2]) throw new Error('Berikan path absolut PhpNode.mjs dari paket php-wasm.');
const { PhpNode } = await import(pathToFileURL(path.resolve(process.argv[2])).href);
const php = new PhpNode({version: '8.3'});
let stdout = '', stderr = '';
php.addEventListener('output', e => { stdout += String(e.detail[0]); });
php.addEventListener('error', e => { stderr += String(e.detail[0]); });
await php.run('<?php echo "";');
const binary = await php.binary;
const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const skip = new Set(['.git','node_modules','vendor','storage','.cache','__pycache__']);
function copy(dir, rel = '') {
    for (const item of fs.readdirSync(dir, {withFileTypes: true})) {
        const source = path.join(dir, item.name);
        const target = '/project/' + path.posix.join(rel, item.name);
        if (item.isDirectory()) {
            if (skip.has(item.name)) continue;
            binary.FS.mkdirTree(target);
            copy(source, path.posix.join(rel, item.name));
        } else if (item.isFile()) {
            binary.FS.writeFile(target, fs.readFileSync(source));
        }
    }
}
binary.FS.mkdirTree('/project');
copy(root);
stdout = ''; stderr = '';
if (process.argv.includes('--regression')) {
    await php.run("<?php require '/project/tools/verifikasi-perbaikan.php';");
    process.stdout.write(stdout);
    if (stderr.trim()) process.stderr.write(stderr);
    process.exitCode = /Ringkasan: \d+ lulus, 0 gagal/.test(stdout) && !stderr.trim() ? 0 : 1;
} else {
await php.run(`<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('/project', FilesystemIterator::SKIP_DOTS));
$total = 0; $failed = 0;
foreach ($files as $file) {
    if (!$file->isFile() || !str_ends_with($file->getPathname(), '.php')) continue;
    $total++;
    try { token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE); }
    catch (Throwable $e) { $failed++; echo 'FAIL ' . $file->getPathname() . ': ' . $e->getMessage() . "\\n"; }
}
echo "Lint PHP: {$total} berkas, {$failed} gagal\\n";
require '/project/app/Services/CbtScoring.php';
$questions = [
    ['id'=>11, 'prompt'=>'Satu', 'options'=>['A'=>'Ya','B'=>'Tidak'], 'correct_answer'=>'A'],
    ['id'=>22, 'prompt'=>'Dua', 'options'=>['A'=>'Ya','B'=>'Tidak'], 'correct_answer'=>'B'],
    ['id'=>33, 'prompt'=>'Tiga', 'options'=>['A'=>'Ya','B'=>'Tidak'], 'correct_answer'=>'A'],
];
$checks = [
    'semua benar' => App\\Services\\CbtScoring::grade($questions, [11=>'A',22=>'B',33=>'A'])['score'] == 100,
    'semua kosong' => App\\Services\\CbtScoring::grade($questions, [])['correct_count'] === 0,
    'pembulatan' => App\\Services\\CbtScoring::grade($questions, [11=>'A'])['score'] === 33.33,
    'soal asing' => App\\Services\\CbtScoring::grade($questions, [999=>'A'])['correct_count'] === 0,
    'snapshot kosong' => App\\Services\\CbtScoring::grade([], [])['score'] === 0.0,
    'kunci disembunyikan' => !isset(App\\Services\\CbtScoring::publicQuestions($questions)[0]['correct_answer']),
    'opsi dipertahankan' => App\\Services\\CbtScoring::publicQuestions($questions)[0]['options'] === $questions[0]['options'],
    'snapshot tidak dimutasi' => $questions[0]['correct_answer'] === 'A',
];
foreach ($checks as $label => $pass) { echo ($pass ? 'PASS ' : 'FAIL ') . $label . "\\n"; if (!$pass) $failed++; }
echo 'CBT logika murni: ' . count(array_filter($checks)) . '/' . count($checks) . " lulus\\n";
echo $failed ? 'RESULT=FAIL' : 'RESULT=PASS';
`);
process.stdout.write(stdout + '\n');
if (stderr.trim()) process.stderr.write(stderr);
if (!stdout.includes('RESULT=PASS') || stderr.trim()) process.exitCode = 1;

}
