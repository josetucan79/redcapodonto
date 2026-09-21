@extends('layouts.layout')

@section('title', 'Permissions')

@section('content')
<ul>
    @foreach($records as $record)
        <?php
        $rewardOptions = $record->getRewardOptions();
        $eligibilityStatus = $record->getEligibilityStatus();
        ?>

        <li>
            <a href="{{ $record->getRecordLink() }}">Record {{ $record->getRecordID() }}</a>

            @foreach($rewardOptions as $id => $option)
                <span> - {{ $option->getDescription() }} </span>
                <span> - {{ ($eligibilityStatus[$id] ?? false) ? 'eligible' : 'not eligible' }}</span>
            @endforeach
        </li>
    @endforeach
</ul>
@endsection