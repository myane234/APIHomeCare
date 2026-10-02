<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Notifikasi' }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { background: #0d9488; color: #ffffff; padding: 25px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 30px; font-size: 15px; line-height: 1.6; color: #334155; }
        .greeting { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #0f766e; }
        .btn-wrapper { text-align: center; margin: 30px 0 20px; }
        .action-button { display: inline-block; padding: 12px 28px; background: #0d9488; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name', 'HomeCare Service') }}</h1>
        </div>

        <div class="content">
            @if(!empty($recipientName))
                <div class="greeting">Halo, {{ $recipientName }}!</div>
            @endif

            <div class="body-text">
                {!! nl2br(e($bodyContent)) !!}
            </div>

            @if(!empty($actionUrl))
                <div class="btn-wrapper">
                    <a href="{{ $actionUrl }}" class="action-button" target="_blank">{{ $actionText ?: 'Buka Aplikasi' }}</a>
                </div>
            @endif

            <p style="font-size: 12px; color: #94a3b8; margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                Email ini dikirim otomatis oleh sistem {{ config('app.name', 'HomeCare') }}. Jika Anda tidak merasa melakukan transaksi atau memiliki akun, silakan abaikan email ini.
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'HomeCare') }}. Semua Hak Dilindungi.
        </div>
    </div>
</body>
</html>
