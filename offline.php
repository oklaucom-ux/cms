<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline | Cyno ERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f8fafc;
            font-family: 'Inter', sans-serif;
            text-align: center;
            margin: 0;
        }
        .offline-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            max-width: 400px;
        }
        .offline-card h1 { margin-top: 0; color: #1e293b; }
        .offline-card p { color: #64748b; margin-bottom: 24px; line-height: 1.5; }
        .retry-btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <h1 style="font-size: 48px; margin-bottom: 10px;">📡</h1>
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection. Don't worry, you can still view any previously loaded pages, but you cannot sync new data right now.</p>
        <button class="retry-btn" onclick="window.location.reload()">Try Again</button>
    </div>
</body>
</html>
