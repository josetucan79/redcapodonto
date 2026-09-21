@extends('layout')

@section('content')
    <span>{{@$args['message']}}</span>
    
    <div>
        <h6 style="margin: 10px 0">Expected steps:</h6>
        <ul>
            <li>user logs in providing REDCap credentials</li>
            <li>"two steps verification" page is displayed</li>
            <li>a Duo session is created</li>
            <li>a Duo store is created and saved in the `redcap_sessions` table: it stores a state (session ID), the REDCap username, and the redirect URL</li>
            <li>user selects the "DUO" option from the list</li>
            <li>the user is redirected to `/twoFA/index.php`</li>
            <li>REDCap starts the "Universal Prompt" process</li>
            <li>the Duo Store is recreated from the session table using the state (DUO session ID)</li>
            <li>REDCap connects (HTTPS) to Duo to perform an "Health Check"</li>
            <li>user is redirected to DUO</li>
            <li>the user is redirected to REDCap</li>
            <li>REDCap connects (HTTPS) to the DUO endpoint: https://api-xxxxxxxx.duosecurity.com to exchange the code for a 2FA result</li>
        </ul>
    <div>

    <div>
        <h6 style="margin: 10px 0">Additional details:</h6>
        <pre><div style="white-space: pre-wrap;">@php(print_r($args))</div></pre>
    </div>
    <div>
    <a href="{{@$redirectUrl}}"><i class="fa-solid fa-arrow-left"></i> go back</a>
    </div>
@endsection

