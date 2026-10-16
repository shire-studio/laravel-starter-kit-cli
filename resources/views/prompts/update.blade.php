# Update this project to starter kit {!! $update->to->version !!}

This project was created from the [{!! $update->to->package !!}]({!! $update->to->repository !!}) starter kit at **{!! $update->from->version !!}**. Bring in the upstream changes up to **{!! $update->to->version !!}** while keeping everything this project has changed since.

## What is in `{!! $updateDirectory !!}/`

- `changes.diff`: the upstream changes from {!! $update->from->version !!} to {!! $update->to->version !!}. Lock files are left out.
- `old/`: the {!! $update->from->version !!} version of every file upstream changed or deleted.
- `new/`: the {!! $update->to->version !!} version of every file upstream changed or added.
@if($update->removedFiles !== [])
- `REMOVED.md`: files that shipped with {!! $update->from->version !!} but no longer exist in this project.
@endif
@if($hasCommitLog)
- `COMMITS.md`: the upstream commit messages between the two versions. Use them to understand why things changed.
@endif

## Apply the changes

For every changed file, compare three versions: `old/` (what this project started from), the project's current file, and `new/` (the upstream target).

- If the project file still matches `old/`, take the `new/` version.
- If the project has customised the file, apply the intent of the upstream change to the customised version. Never throw away the project's own changes.
- Add files that upstream added, unless they belong to a feature this project removed.
- Delete files that upstream deleted, unless the project has changed them substantially. Ask the user if you are unsure.

## Respect removed features

This project may have deliberately removed starter kit features, for example authentication. Never bring them back.

- Never restore a file listed in `REMOVED.md`.
- Skip changes that only touch code the project removed, including code removed from files that still exist, such as routes, navigation, config and tests.
- Treat new upstream files that belong to a removed feature as removed too.
- When you cannot tell whether something was removed on purpose, ask the user instead of guessing.
@if($removedFeatures !== [])

These features were removed from this project on purpose in earlier updates:

@foreach($removedFeatures as $removedFeature)
- {!! $removedFeature !!}
@endforeach
@endif

## Dependencies and checks

- Apply changes to `composer.json` and `package.json` by hand, then regenerate the lock files with `composer update` for the changed packages and `pnpm install`.
- Run `composer transform-types` so the generated TypeScript types match the updated PHP code.
- Run the project's tests, linters and type checks, and fix anything the update broke.

## When you are done

- Do not commit anything. The user reviews the changes before committing.
@if($isManual)
- Copy `{!! $updateDirectory !!}/{!! $manifestFilename !!}` to `{!! $manifestFilename !!}` in the project root. If you found features this project removed on purpose that are not recorded yet, add a short description of each to its `removed` list.
- Delete the `{!! $updateDirectory !!}/` directory.
@else
- Do not edit `{!! $manifestFilename !!}` or delete `{!! $updateDirectory !!}/`. The CLI does both once the user confirms the update.
- If you found features this project removed on purpose that are not recorded above, write a short description of each to `{!! $updateDirectory !!}/removed.json` as a JSON array of strings, for example `["Authentication (login, registration and two factor)"]`.
- Summarise what you changed, what you skipped and why, then tell the user to exit this session so the CLI can finish the update.
@endif
