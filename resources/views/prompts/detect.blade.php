# Find the starter kit version this project was created from

This project was created from the [{!! $release->package !!}]({!! $release->repository !!}) starter kit, but it has no `{!! $manifestFilename !!}` file recording which release. Work out the release it most likely started from.

These are the stable releases, newest first:

@foreach($versions as $version)
- {!! $version !!}
@endforeach

## How to find it

- Check the project's git history first. A first commit such as `chore: create project from starter kit v1.4.0` names the release directly.
- Compare the project with candidate releases. Download one without installing anything with `composer create-project {!! $release->package !!} {!! $updateDirectory !!}/releases/<version> <version> --no-install --no-scripts`. Keep downloads inside `{!! $updateDirectory !!}/`, which the CLI removes afterwards.
- Compare files that rarely change in projects, such as config files, `composer.json` and `package.json` constraints, and the starter kit's own components.
- This project may have removed starter kit features or customised files heavily. Missing or changed files are expected, so do not let them lower your confidence on their own.

## When you are done

- Do not change any files in this project outside `{!! $updateDirectory !!}/`.
- Write the version, exactly as it appears in the list above, to `{!! $updateDirectory !!}/detected-version` and nothing else.
- Tell the user which version you chose and why, then tell them to exit this session so the CLI can continue.
