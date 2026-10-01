# Issue tracker: GitHub

Issues and specs for this repo live as GitHub issues. Use the `gh` CLI for all operations.

## Repository

- GitHub repository: `ryuuwiz/unms`
- Infer the repository from the checkout; `gh` does this automatically when run inside the clone.

## Conventions

- **Create an issue**: `gh issue create --title "..." --body "..."`
- **Read an issue**: `gh issue view <number> --comments`
- **List issues**: `gh issue list --state open --json number,title,body,labels,comments`
- **Comment on an issue**: `gh issue comment <number> --body "..."`
- **Apply / remove labels**: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`
- **Close**: `gh issue close <number> --comment "..."`

## Pull requests as a triage surface

**PRs as a request surface: no.** External pull requests are not included in the issue triage queue by default.

## Wayfinding operations

- **Map**: a single issue labelled `wayfinder:map`, holding the Notes / Decisions-so-far / Fog body.
- **Child ticket**: link child issues to the map as GitHub sub-issues when supported. Otherwise add `Part of #<map>` at the top of the child body. Use `wayfinder:<type>` labels for `research`, `prototype`, `grilling`, or `task`.
- **Blocking**: use GitHub native issue dependencies when available. Otherwise add a `Blocked by: #<n>, #<n>` line at the top of the child body.
- **Frontier query**: among open map children, choose the first unblocked and unassigned issue in map order.
- **Claim**: assign the issue to `@me`.
- **Resolve**: comment with the answer, close the issue, then append a context pointer to the map's Decisions-so-far.

## Skill operations

When a skill says to publish to the issue tracker, create a GitHub issue. When it says to fetch a relevant ticket, run `gh issue view <number> --comments`.
