// PreToolUse hook: guards Claude's git and PR commands.
// - Commits must start "CLAUDE - ".
// - PRs must follow the template, and only a human may tick the attestation.
//
// The push-time quality gate (Pint, PHPStan, tests + coverage, npm ci, build)
// is removed for now. It ran in the working checkout, so `npm ci` collided with
// the running Vite dev server, and it checked the working tree rather than
// the commit being pushed. CI still runs every check on PRs. To bring it back,
// run it in an isolated checkout of the pushed commit (see PROCESS.md entry 11).
//
// Exit 0 = allow, exit 2 = block (stderr is shown to Claude). Claude Code
// treats any other exit code as "allow", so a crash must still exit 2.
const crashed = (e) => {
    process.stderr.write(`HOOK FAILED: crashed, command blocked.\n\n${e?.stack ?? e}\n`);
    process.exit(2);
};
process.on('uncaughtException', crashed);
process.on('unhandledRejection', crashed);

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

// PRs: GitHub only auto-fills .github/pull_request_template.md in the web UI,
// so `gh pr create --body ...` would skip it. Enforce the template here, and
// never let Claude tick the human attestation box.
const prCmd = cmd.match(/(^|[\s;&|(])gh\s+pr\s+(create|edit)\b/)?.[2];
if (prCmd) {
    const bodyFile = cmd.match(/--body-file\s+("[^"]+"|'[^']+'|\S+)/)?.[1]?.replace(/^["']|["']$/g, '');
    let text = cmd;
    try { if (bodyFile) text += '\n' + readFileSync(resolve(input.cwd || process.cwd(), bodyFile), 'utf8'); } catch {}
    const prBlock = (why) => {
        process.stderr.write(`PR BLOCKED: ${why}\nFollow the "Pull requests" section of CLAUDE.md and .github/pull_request_template.md.\n`);
        process.exit(2);
    };
    if (/\[[xX]\]\s*I am aware this PR was written with AI/.test(text)) prBlock('only a human may tick the attestation box.');
    if (prCmd === 'create') {
        const missing = ['Task', 'Objective', 'Testing', 'Notes', 'Attestation'].filter((h) => !new RegExp(`^## ${h}\\s*$`, 'm').test(text));
        if (missing.length) prBlock(`body is missing section(s): ${missing.join(', ')}.`);
        if (!/- \[ \] I am aware this PR was written with AI/.test(text)) prBlock('body must include the unticked attestation line, worded exactly as in the template.');
        if (!/(--label|-l)\s+["']?claude\b/.test(cmd)) prBlock('add the label: --label claude.');
    }
}

process.exit(0);
