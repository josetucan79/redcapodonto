<p>{{$lang['global_21']}}</p>
<p>The Adjudication process is completed for project <strong>{{$project_id}}</strong></p>
<table border='1' style='border-collapse:collapse'>
  <thead>
    <tr>
    @foreach(array_keys($data) as $key)
      <th style="text-transform: uppercase">{{$key}}</th>
    @endforeach
    </tr>
  </thead>
  <tbody>
    <tr>
      @foreach($data as $key => $value)
      <td><span title="{{$key}}">{{$value}}</span></td>
      @endforeach
    </tr>
  </tbody>
</table>
@if(count($errors)>0)
<h3>Errors</h3>
<ul>
@foreach ($errors as $error)
  <li>{{$error}}</li>
@endforeach
</ul>
@endif