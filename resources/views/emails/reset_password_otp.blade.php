<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Reset Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #1e3a8a;
            padding: 24px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
        }
        .otp-box {
            text-align: center;
            margin: 25px 0;
        }
        .otp-code {
            display: inline-block;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #1e3a8a;
            background-color: #eff6ff;
            padding: 12px 24px;
            border-radius: 8px;
            border: 1px dashed #3b82f6;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        .warning {
            font-size: 13px;
            color: #dc2626;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Password {{ $portalName }}</h1>
        </div>
        <div class="content">
            <p>Halo,</p>
            <p>Kami menerima permintaan untuk mengatur ulang password akun Anda di <strong>Home Care</strong>. Gunakan kode OTP berikut untuk melanjutkan proses verifikasi:</p>
            
            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
            </div>

            <p>Kode OTP ini hanya berlaku selama <strong>10 menit</strong>. Jangan bagikan kode ini kepada siapapun demi keamanan akun Anda.</p>
            <p class="warning">Jika Anda tidak merasa melakukan permintaan reset password ini, abaikan email ini.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Home Care. Hak cipta dilindungi undang-undang.</p>
        </div>
    </div>
</body>
</html>
