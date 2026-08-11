<?php
http_response_code(404);
echo '<!DOCTYPE html>
<html>
<head>
    <title>404 Not Found</title>
    <meta http-equiv="refresh" content="3;url=https://www.google.com/">
    <style>
        body { font-family: Arial, sans-serif; background: #fff; color: #333; text-align: center; margin-top: 10%; }
        h1 { font-size: 48px; margin-bottom: 10px; }
        p { font-size: 20px; }
    </style>
</head>
<body>
    <h1>404 Not Found</h1>
    <p>The page you are looking for could not be found.</p>
    <p>You will be redirected to Google in 3 seconds.</p>
</body>
</html>';
exit();
?>
