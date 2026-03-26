<!DOCTYPE html>
<html lang="id">
<body style="margin:0; padding:24px; font-family:Arial, Helvetica, sans-serif; color:#222; background:#f6f0e6;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border-radius:16px; border:1px solid #e1d7c8; overflow:hidden;">
        <div style="padding:24px 24px 0;">
            <p style="margin:0 0 12px; color:#8f3d17; font-size:12px; letter-spacing:.14em; text-transform:uppercase;">POSI</p>
            <h1 style="margin:0 0 18px; font-size:28px;">{{ $personalizedSubject }}</h1>
            <div style="font-size:16px; line-height:1.7; color:#2f2b28;">
                {!! nl2br(e($personalizedBody)) !!}
            </div>
        </div>
        <div style="padding:20px 24px 24px; font-size:12px; color:#7b736d;">
            Anda menerima email ini karena data Anda pernah tercatat pada sistem POSI. Jika tidak ingin menerima informasi berikutnya, balas email ini dengan subjek STOP atau hubungi admin POSI untuk dikeluarkan dari daftar informasi.
        </div>
    </div>
</body>
</html>
