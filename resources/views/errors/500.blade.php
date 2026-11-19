<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Server Error · Mash Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="card shadow-sm border-0 p-4 p-md-5 text-center" style="max-width: 500px; border-radius: 0.75rem;">
        <div class="text-uppercase small fw-bold text-danger mb-1">Error 500</div>
        <h1 class="h3 fw-bold mb-2">Something Went Wrong</h1>
        <p class="text-muted mb-4">
            We encountered an unexpected server error. Please try again in a moment.
        </p>
        <a href="{{ url('/') }}" class="btn btn-dark">Return to Home</a>
    </div>
</body>
</html>
