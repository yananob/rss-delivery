@extends('layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>RSSフィード一覧</h1>
    <a href="{{ $basePath }}/new" class="btn btn-primary">新規追加</a>
</div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>
                <a href="{{ $basePath }}/?sort=name&direction={{ $currentSort === 'name' && $currentDirection === 'asc' ? 'desc' : 'asc' }}">
                    名前
                    @if($currentSort === 'name')
                        {{ $currentDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </a>
            </th>
            <th>
                <a href="{{ $basePath }}/?sort=url&direction={{ $currentSort === 'url' && $currentDirection === 'asc' ? 'desc' : 'asc' }}">
                    URL
                    @if($currentSort === 'url')
                        {{ $currentDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </a>
            </th>
            <th>
                <a href="{{ $basePath }}/?sort=notify_method&direction={{ $currentSort === 'notify_method' && $currentDirection === 'asc' ? 'desc' : 'asc' }}">
                    通知方法
                    @if($currentSort === 'notify_method')
                        {{ $currentDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </a>
            </th>
            <th>
                <a href="{{ $basePath }}/?sort=notify_bot&direction={{ $currentSort === 'notify_bot' && $currentDirection === 'asc' ? 'desc' : 'asc' }}">
                    通知BOT
                    @if($currentSort === 'notify_bot')
                        {{ $currentDirection === 'asc' ? '▲' : '▼' }}
                    @endif
                </a>
            </th>
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
