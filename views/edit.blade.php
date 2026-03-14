@extends('layout')

@section('content')
<h1>{{ isset($feed) ? 'フィード編集' : 'フィード新規作成' }}</h1>

<form action="{{ isset($feed) ? $basePath . '/edit/' . $feed->getId() : $basePath . '/new' }}" method="POST" class="mt-4">
    <div class="mb-3">
        <label for="name" class="form-label">フィード名</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ $feed ? $feed->getName() : '' }}" required>
    </div>
    <div class="mb-3">
        <label for="url" class="form-label">URL</label>
        <input type="url" class="form-control" id="url" name="url" value="{{ $feed ? $feed->getUrl() : '' }}" required>
    </div>
    <div class="mb-3">
        <label for="notify_method" class="form-label">通知方法</label>
        <select class="form-select" id="notify_method" name="notify_method" required>
            <option value="LINE" {{ ($feed && $feed->getNotifyMethod() == 'LINE') ? 'selected' : '' }}>LINE</option>
            <option value="Save" {{ ($feed && $feed->getNotifyMethod() == 'Save') ? 'selected' : '' }}>Save (Raindrop)</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="notify_bot" class="form-label">通知BOT ID (LINEの場合のみ)</label>
        <input type="text" class="form-control" id="notify_bot" name="notify_bot" value="{{ $feed ? $feed->getNotifyBot() : '' }}">
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ isset($feed) ? '更新' : '保存' }}</button>
        <a href="{{ $basePath }}/" class="btn btn-outline-secondary">キャンセル</a>
    </div>
</form>
@endsection
