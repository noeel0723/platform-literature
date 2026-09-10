@props(['user', 'current' => 'profile'])

@php($navigation = app(App\Support\ProfilePagePresenter::class)->navigation($user, $current, auth()->user()))

<script type="application/json" data-react-profile-subnav-props>{!! Illuminate\Support\Js::encode(['navigation' => $navigation]) !!}</script>
<div {{ $attributes }} data-react-profile-subnav></div>
