@extends('parcel.layout')

@section('content')
    @if($error)
        <h3>Error</h3>
        <p>{{$error}}</p>
    @else
    <ul>
        <li><span>id:</span><span>{{$parcel->id}}</span></li>
        <li><span>to:</span><span>{{$parcel->to}}</span></li>
        <li><span>from:</span><span>{{$parcel->from}}</span></li>
        <li><span>subject:</span><span>{{$parcel->subject}}</span></li>
        <li><span>body:</span><span>{{$parcel->body}}</span></li>
        <li><span>lifespan:</span><span>{{$parcel->lifespan}}</span></li>
        <li><span>createdAt:</span><span>{{$parcel->createdAt}}</span></li>
        <li><a href="{{$parcel->getURL()}}">{{$parcel->id}}</a></li>
    </ul>
    @endif
@endsection