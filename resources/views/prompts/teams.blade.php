# Add teams to this project

This project was created from the {!! $addition->release->kit->label() !!} starter kit ([{!! $addition->release->kit->package() !!}]({!! $addition->release->repository !!})) at **{!! $addition->release->version !!}** without teams. The starter kit ships a teams variant of the same release, and this project is moving to it. The starter kit CLI has already applied every teams change that did not conflict with this project's own changes.
@if($addition->unresolvedFiles !== [])
The files listed under "Files to merge" are left: this project changed them, so the teams changes have to be merged into the project's version.
@endif
@if($addition->filesToReview !== [])
This project removed some starter kit features on purpose, so the new team files listed under "Check new files against removed features" need checking too.
@endif

## What is in `{!! $updateDirectory->name !!}/`

- `{!! $updateDirectory->relative($updateDirectory->changes()) !!}`: every change the teams variant makes to {!! $addition->release->version !!}. Lock files are left out, because teams add no dependencies.
- `{!! $updateDirectory->relative($updateDirectory->oldFiles()) !!}/`: the {!! $addition->release->version !!} version without teams of every file listed below that it has.
- `{!! $updateDirectory->relative($updateDirectory->newFiles()) !!}/`: the {!! $addition->release->version !!} teams version of every file listed below that it has.

@if($addition->unresolvedFiles !== [])
## Files to merge

@foreach($addition->unresolvedFiles as $unresolvedFile)
- `{!! $unresolvedFile !!}`
@endforeach

For each file, compare three versions: the old version (what this project started from), the project's current file, and the new version (with teams).

- If the project has customised the file, apply the intent of the teams change to the customised version. Never throw away the project's own changes.
- If the new version is missing, teams delete the file. Delete it unless the project has changed it substantially, and ask the user if you are unsure.
- If the old version is missing, teams add the file but the project already has its own file at that path. Combine the two, or ask the user.
- If the project no longer has the file, the project removed it. Do not restore it. Apply the teams change somewhere else only if teams cannot work without it, and ask the user first.

@endif
@if($addition->filesToReview !== [])
## Check new files against removed features

The CLI added these new team files to the project:

@foreach($addition->filesToReview as $fileToReview)
- `{!! $fileToReview !!}`
@endforeach

Delete any of them that only serve a feature this project removed, together with anything that refers to them, such as routes, navigation and tests. Keep the ones teams need. Ask the user when you are not sure.

@endif
Every other teams change is already in the project. Do not apply it again.
@if($removedFeatures !== [])

These features were removed from this project on purpose. Never bring them back:

@foreach($removedFeatures as $removedFeature)
- {!! $removedFeature !!}
@endforeach
@endif

## Dependencies and checks

- Run `composer dump-autoload` so the new team classes load.
- The team migrations only add tables and columns, and a data migration gives every existing user a personal team. Do not run them against the user's database without asking.
@if($addition->release->kit->generatesTypes())
- Run `composer transform-types` so the generated TypeScript types include the team data.
@endif
- Run the project's tests, linters and type checks, and fix anything teams broke. Tests the project added may expect the flow without teams, for example the dashboard at `/dashboard` instead of `/{team}/dashboard`. Update them to the teams flow.

## When you are done

- Do not commit anything. The user reviews the changes before committing.
- Summarise what you changed, what you skipped and why.

## Finish adding teams

If the starter kit CLI started this session, stop here and tell the user to exit it. The CLI records that the project has teams and removes `{!! $updateDirectory->name !!}/` once they confirm.

Otherwise, finish by hand:

1. Copy `{!! $updateDirectory->relative($updateDirectory->manifest()) !!}` to `{!! $updateDirectory->manifestFilename !!}` in the project root.
2. Delete the `{!! $updateDirectory->name !!}/` directory.
