<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Contact Form Submission from {{ $first_name }} {{$last_name}}</title>
</head>
<body>
    <h1>Contact Form Submission from {{ $first_name }} {{$last_name}}</h1>
    <p>Email: {{ $email }}</p>
    <p>Phone: {{ $phone }}</p>
    <p>message: {{ $message_text }}</p>
</body>
</html>
