<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome to FarmConsul</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #2d6a4f; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: .5px; }
        .body { padding: 40px; color: #333333; line-height: 1.7; }
        .body h2 { color: #2d6a4f; margin-top: 0; }
        .credentials { background: #f0faf4; border: 1px solid #b7e4c7; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .credentials p { margin: 6px 0; font-size: 15px; }
        .credentials strong { display: inline-block; min-width: 120px; color: #1b4332; }
        .btn { display: inline-block; margin-top: 24px; padding: 14px 32px; background: #2d6a4f; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: bold; letter-spacing: .3px; }
        .notice { margin-top: 24px; padding: 16px 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; font-size: 14px; color: #856404; }
        .footer { padding: 24px 40px; text-align: center; font-size: 13px; color: #888888; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🌱 FarmConsul</h1>
    </div>

    <div class="body">
        <h2>Hello, {{ $newUser->name }}!</h2>

        <p>
            You have been added to a farm on <strong>FarmConsul</strong> as a
            <strong>{{ ucfirst($farmRole) }}</strong>.
            Your account has been created and you can log in using the temporary credentials below.
        </p>

        <div class="credentials">
            <p><strong>Email:</strong> {{ $newUser->email }}</p>
            <p><strong>Temporary Password:</strong> {{ $temporaryPassword }}</p>
        </div>

        <p>
            For security, please set your own password immediately by clicking the button below.
            This link will expire in <strong>60 minutes</strong>.
        </p>

        <a href="{{ $resetUrl }}" class="btn">Set My Password</a>

        <div class="notice">
            ⚠️ If you did not expect this email, you can safely ignore it.
            No action is needed unless you want to access the platform.
        </div>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} FarmConsul. All rights reserved.<br />
        If the button above does not work, copy and paste this link into your browser:<br />
        <a href="{{ $resetUrl }}" style="color:#2d6a4f; word-break:break-all;">{{ $resetUrl }}</a>
    </div>
</div>
</body>
</html>

