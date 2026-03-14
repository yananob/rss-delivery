@extends('layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>RSSフィード一覧</h1>
    <a href="{{ $basePath }}/new" class="btn btn-primary">新規追加</a>
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>名前</th>
            <th>URL</th>
            <th>通知方法</th>
            <th>通知BOT</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        @foreach($feeds as $feed)
        <tr>
            <td>{{ $feed->getName() }}</td>
            <td><a href="{{ $feed->getUrl() }}" target="_blank">{{ $feed->getUrl() }}</a></td>
            <td>{{ $feed->getNotifyMethod() }}</td>
            <td>{{ $feed->getNotifyBot() ?? '-' }}</td>
            <td>
                <a href="{{ $basePath }}/edit/{{ $feed->getId() }}" class="btn btn-sm btn-outline-secondary">編集</a>
                <form action="{{ $basePath }}/delete/{{ $feed->getId() }}" method="POST" class="d-inline" onsubmit="return confirm('本当に削除しますか？');">
                    <button type="submit" class="btn btn-sm btn-outline-danger">削除</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection
