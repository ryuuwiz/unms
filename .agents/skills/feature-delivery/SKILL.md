---
name: feature-delivery
description: "Run a feature from an ambiguous idea to reviewed code through /grill-with-docs, /to-spec, /to-tickets, /implement, and /code-review. Use for planned feature work that needs documented decisions, dependency-aware tickets, test-driven implementation, and a final two-axis review."
disable-model-invocation: true
---

# Feature Delivery

Use this workflow for feature work that should leave a durable decision trail and a reviewable implementation. Keep the product discovery, specification, and ticketing phases in one context so each phase can build on the previous one. Implement tickets in fresh contexts when practical.

## Preconditions

- Confirm the repository has been configured for the engineering skills, including its issue tracker and domain-document locations. If not, run `/setup-matt-pocock-skills` first.
- Read the repository's applicable instructions and existing domain documents before making technical commitments.
- Use this pipeline for a shaped feature or change request. Route raw bug reports, incoming requests, and very large efforts through the repository's triage, diagnosis, or wayfinder flow before starting here.

## Procedure

### 1. Sharpen the idea

Run `/grill-with-docs`.

- Interview until the problem, users, constraints, terminology, and success conditions are concrete.
- Resolve domain language and record durable decisions in the repository's glossary or ADRs when the skill calls for them.
- Do not start implementation while a material product or architectural question remains unanswered.
- At the end, capture the decisions and unresolved risks that `/to-spec` must preserve.

Completion check: the user can describe what will change, for whom, why it matters, and what is explicitly out of scope.

### 2. Synthesize the specification

Run `/to-spec` using the grilled conversation and repository context.

- Do not restart the interview; synthesize what is already known.
- Define the problem, solution, user stories, implementation decisions, testing decisions, out-of-scope items, and further notes.
- Prefer the highest existing test seam and record only decisions, not brittle file paths or implementation trivia.
- Publish the approved spec to the configured issue tracker.

Completion check: a reviewer could determine from the spec whether an implementation satisfies the requested behavior.

### 3. Split the work into tickets

Run `/to-tickets` against the approved spec.

- Create narrow, complete tracer-bullet slices that are independently verifiable.
- Put blockers first and declare only genuine blocking edges.
- Use expand-migrate-contract sequencing for wide mechanical refactors.
- Present the proposed tickets and wait for the user's approval of granularity and dependencies before publishing them.
- Publish approved tickets with the repository's configured tracker and `ready-for-agent` status where that convention applies.

Completion check: every ticket has a user-visible outcome, acceptance criteria, a clear status, and an accurate `Blocked by` list.

### 4. Implement the tickets

Run `/implement` once per ticket, working the dependency frontier from blockers to dependents.

- Give each implementation context one self-contained ticket and its spec references.
- Use `/tdd` at the agreed seams where behavior can be tested meaningfully.
- Run the narrowest relevant test or type check after each change, then the full suite when the ticket set is complete.
- Keep unrelated cleanup out of the ticket unless it is required to make the behavior correct.
- Commit completed work according to the repository's workflow, and record any discovered spec or ticket changes before proceeding.
- Clear or compact context between tickets when the next ticket does not need the previous implementation details.

Completion check: each ticket's acceptance criteria are demonstrated by tests or another explicit verification, and the implementation is committed on the intended branch.

### 5. Review the result

Run `/code-review` against the intended fixed point, normally `main` or the merge base.

- Review the diff separately for documented repository standards and fidelity to the originating spec.
- Treat missing behavior, scope creep, security issues, regressions, and high-severity standards violations as blockers.
- Fix actionable findings in the implementation, rerun focused tests, and repeat the review when the diff changes materially.
- Report remaining test gaps or deferred findings explicitly.

Completion check: the final review has a known spec source, a known fixed point, no unresolved blocking findings, and verification results attached to the delivery summary.

## Handoff Rules

- `/grill-with-docs` produces decisions and domain vocabulary for `/to-spec`.
- `/to-spec` produces the contract for `/to-tickets`.
- `/to-tickets` produces the dependency graph for `/implement`.
- `/implement` produces the diff and verification evidence for `/code-review`.
- Never skip an approval gate by silently inventing product requirements.
- If a phase cannot complete because required information or tooling is missing, stop at that boundary, state the exact blocker, and preserve the artifacts already produced.