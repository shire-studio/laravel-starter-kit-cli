# Removed starter kit files

These files shipped with starter kit {!! $version !!} but no longer exist in this project. Treat them, and the features they belong to, as deliberately removed.
@foreach($removedFilesByDirectory as $directory => $removedFiles)

## {!! $directory !!}

@foreach($removedFiles as $removedFile)
- {!! $removedFile !!}
@endforeach
@endforeach
