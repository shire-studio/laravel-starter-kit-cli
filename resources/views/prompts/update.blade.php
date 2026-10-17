# Update this project to starter kit {!! $update->to->version !!}

This project was created from the [{!! $update->to->package !!}]({!! $update->to->repository !!}) starter kit at **{!! $update->from->version !!}**. Bring in the upstream changes up to **{!! $update->to->version !!}** while keeping everything this project has changed since.

## What is in `{!! $updateDirectory->name !!}/`

- `{!! $updateDirectory->relative($updateDirectory->changes()) !!}`: the upstream changes from {!! $update->from->version !!} to {!! $update->to->version !!}. Lock files are left out.
- `{!! $updateDirectory->relative($updateDirectory->oldFiles()) !!}/`: the {!! $update->from->version !!} version of every file upstream changed or deleted.
- `{!! $updateDirectory->relative($updateDirectory->newFiles()) !!}/`: the {!! $update->to->version !!} version of every file upstream changed or added.
@if($update->removedFiles !== [])
- `{!! $updateDirectory->relative($updateDirectory->removedFiles()) !!}`: files that shipped with {!! $update->from->version !!} but no longer exist in this project.
@endif
@if($hasCommitLog)
- `{!! $updateDirectory->relative($updateDirectory->commitLog()) !!}`: the upstream commit messages between the two versions. Use them to understand why things changed.
@endif

## Apply the changes

For every changed file, compare three versions: the old version (what this project started from), the project's current file, and the new version (the upstream target).

- If the project file still matches the old version, take the new version.
- If the project has customised the file, apply the intent of the upstream change to the customised version. Never throw away the project's own changes.
- Add files that upstream added, unless they belong to a feature this project removed.
- Delete files that upstream deleted, unless the project has changed them substantially. Ask the user if you are unsure.

## Respect removed features

This project may have deliberately removed starter kit features, for example authentication. Never bring them back.

- Never restore a file listed in `{!! $updateDirectory->relative($updateDirectory->removedFiles()) !!}`.
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
- If you found features this project removed on purpose that are not recorded above, write a short description of each to `{!! $updateDirectory->relative($updateDirectory->removedFeatures()) !!}` as a JSON array of strings, for example `["Authentication (login, registration and two factor)"]`.
- Summarise what you changed, what you skipped and why.

## Finish the update

If the starter kit CLI started this session, stop here and tell the user to exit it. The CLI records the update and removes `{!! $updateDirectory->name !!}/` once they confirm.

Otherwise, finish the update by hand:

1. Copy `{!! $updateDirectory->relative($updateDirectory->manifest()) !!}` to `{!! $updateDirectory->manifestFilename !!}` in the project root. If `{!! $updateDirectory->relative($updateDirectory->removedFeatures()) !!}` exists, add each feature in it to the `removed` list of the copied file.
2. Delete the `{!! $updateDirectory->name !!}/` directory.
