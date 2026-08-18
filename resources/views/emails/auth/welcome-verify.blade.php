@extends('emails.layout')

@section('title', 'Welcome to Purplelist')
@section('preheader', 'Your calmer place for tasks is ready. Confirm your email address.')
@section('eyebrow', 'Welcome')
@section('heading', 'A clearer day starts here.')

@section('content')
    <p style="margin:0 0 16px;color:#566471;font-size:16px;line-height:1.7;">Hi {{ $user->name }},</p>
    <p style="margin:0;color:#566471;font-size:16px;line-height:1.7;">
        Welcome to Purplelist. Your Inbox is ready whenever you are. Confirm your email address so we know we can reach you when you need account help.
    </p>
@endsection

@section('actionUrl', $verificationUrl)
@section('actionLabel', 'Confirm email address')

@section('afterAction')
    <p style="margin:0;color:#7b8791;font-size:13px;line-height:1.65;">
        Confirmation is optional and will not prevent you from signing in. If you did not create this account, you can safely ignore this email.
    </p>
@endsection
