// PreToolUse hook: blocks `git push` and `gh pr create|merge|ready` until the
// same checks CI runs pass locally. Catching failures here keeps broken work
// off GitHub and saves CI minutes.
//
// Exit 0 = allow, exit 2 = block (stderr is shown to Claude). Claude Code
// treats any other exit code as "allow", so a crash must still exit 2.
const crashed = (e) => {
    process.stderr.write(`QUALITY GATE FAILED: hook crashed, push blocked.\n\n${e?.stack ?? e}\n`);
    process.exit(2);
};
process.on('uncaughtException', crashed);
process.on('unhandledRejection', crashed);

import { execSync, spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const input = JSON.parse(await new Response(process.stdin).text() || '{}');
const cmd = input.tool_input?.command ?? '';

// Every commit Claude makes starts "CLAUDE - ", so the history shows which
// commits are AI-made and which are Adam's. Only Claude's commands reach this
// hook, so Adam's own commits are unaffected.
if (/(^|[\s;&|(])git\s+(-C\s+\S+\s+)?commit\b/.test(cmd) && !/--no-edit\b/.test(cmd) && !/CLAUDE - /.test(cmd)) {
    process.stderr.write('COMMIT BLOCKED: the commit message must start with "CLAUDE - " (see CLAUDE.md).\n');
    process.exit(2);
}

if (!/(^|[\s;&|(])(git\s+(-C\s+\S+\s+)?push|gh\s+pr\s+(create|merge|ready))\b/.test(cmd)) process.exit(0);

// Work out which checkout is being pushed: an explicit `git -C <dir>` or
// `cd <dir>` in the command wins, otherwise the session's working directory.
const cwd = input.cwd || process.cwd();
const dirHint = cmd.match(/git\s+-C\s+("[^"]+"|'[^']+'|\S+)/)?.[1] ?? cmd.match(/(?:^|[;&]\s*)cd\s+("[^"]+"|'[^']+'|[^\s;&]+)/)?.[1];
const start = dirHint ? resolve(cwd, dirHint.replace(/^["']|["']$/g, '')) : cwd;
const root = execSync('git rev-parse --show-toplevel', { cwd: start, encoding: 'utf8' }).trim();

const block = (title, out = '') => {
    process.stderr.write(`QUALITY GATE FAILED: ${title}\n\n${out.split('\n').slice(-80).join('\n')}\n\nFix the failure, then retry. Do not bypass this hook.\n`);
    process.exit(2);
};

// CI is the source of truth: read the coverage threshold and PHP version from it.
const ci = readFileSync(`${root}/.github/workflows/ci.yml`, 'utf8');
const min = ci.match(/--min=(\d+(\.\d+)?)/)?.[1];
if (!min) block('no --min=<n> coverage threshold found in .github/workflows/ci.yml');

// Machine parity: a local pass only means something if it ran on the same PHP
// minor version with the same coverage driver (PCOV) as CI.
const ciPhp = ci.match(/php-version:\s*'?([\d.]+)/)?.[1];
const php = spawnSync('php -r "echo PHP_MAJOR_VERSION.\'.\'.PHP_MINOR_VERSION, \' \', extension_loaded(\'pcov\') ? \'pcov\' : \'no-pcov\';"', { shell: true, encoding: 'utf8' });
if (php.status !== 0) block('PHP not found on PATH (install PHP ' + ciPhp + ' with PCOV, as in README)', php.stderr ?? '');
const [localPhp, driver] = php.stdout.trim().split(' ');
if (ciPhp && localPhp !== ciPhp) block(`local PHP ${localPhp} does not match CI PHP ${ciPhp}`);
if (driver !== 'pcov') block('PCOV is not loaded. CI measures coverage with PCOV; install/enable it locally so results match');

const steps = [
    ['composer install', 'composer install --no-interaction --quiet'],
    // `php vendor/bin/x` rather than `vendor/bin/x`: cmd.exe can't run the latter.
    ['Pint', 'php vendor/bin/pint --test'],
    ['PHPStan', 'php vendor/bin/phpstan analyse --no-progress --memory-limit=1G'],
    [`tests + coverage (min ${min}%)`, `php artisan test --coverage --min=${min}`],
    ['npm ci', 'npm ci --no-audit --no-fund'],
    ['build', 'npm run build'],
];

for (const [name, line] of steps) {
    const r = spawnSync(line, { cwd: root, shell: true, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    if (r.error || r.status !== 0) block(name, `${r.stdout ?? ''}${r.stderr ?? ''}${r.error ?? ''}`);
}

process.stderr.write('Quality gate passed.\n');
process.exit(0);
