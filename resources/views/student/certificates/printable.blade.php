@php
    $academyName = \App\Models\AcademySetting::getValue('academy_name', 'Mash Academy') ?: 'Mash Academy';
    $logoSrc = \App\Models\AcademySetting::logoDataUri() ?: \App\Models\AcademySetting::logoUrl();
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Certificate {{ $certificate->certificate_code }} — {{ $academyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px;
            background: #ffffff;
            color: #111111;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .outer {
            width: 100%;
            max-width: 920px;
            background: #ffffff;
            border: 10px double #0a192f;
            padding: 28px;
        }
        .inner {
            border: 2px solid #1e3a8a;
            padding: 48px 40px;
            text-align: center;
        }
        .brand {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #0a192f;
            margin-bottom: 14px;
        }
        .eyebrow {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 24px;
        }
        .lead { color: #4b5563; font-size: 15px; margin: 10px 0; }
        .student { font-size: 38px; font-weight: 800; color: #000000; margin: 14px 0; }
        .course { font-size: 24px; font-weight: 700; color: #1e3a8a; margin: 14px 0 32px; }
        .meta {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
            margin-top: 24px;
            font-size: 14px;
        }
        .meta-item { flex: 1; }
        .meta-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; }
        .meta-val { font-weight: 700; margin-top: 4px; color: #111111; }
        .code { font-family: monospace; color: #1e3a8a; }
        .verify { margin-top: 24px; font-size: 12px; color: #6b7280; }
        @media print {
            body { padding: 0; }
            @page { size: landscape; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="inner">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="{{ $academyName }}" style="max-height: 64px; max-width: 220px; object-fit: contain; margin-bottom: 10px;">
            @else
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 10px; background: linear-gradient(135deg, #000000 0%, #1e3a8a 100%); color: #ffffff; font-weight: 800; font-size: 22px; margin-bottom: 10px;">MA</div>
            @endif
            <div class="brand">{{ $academyName }}</div>
            <div class="eyebrow">Certificate of Completion</div>
            <p class="lead">This certifies that</p>
            <div class="student">{{ $student->name }}</div>
            <p class="lead">has successfully completed all coursework and the Capstone Final Project for</p>
            <div class="course">{{ $course->title }}</div>

            <div class="meta">
                <div class="meta-item">
                    <div class="meta-label">Completion Date</div>
                    <div class="meta-val">{{ $certificate->issued_at->format('F d, Y') }}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Verification ID</div>
                    <div class="meta-val code">{{ $certificate->certificate_code }}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Issued By</div>
                    <div class="meta-val">{{ $academyName }}</div>
                </div>
            </div>

            <div class="verify">
                Verify at {{ route('certificates.verify', ['code' => $certificate->certificate_code]) }}
            </div>
        </div>
    </div>
</body>
</html>
