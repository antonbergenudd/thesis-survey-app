<!DOCTYPE html>
<html>
<head>
    <title>Survey</title>
</head>
<body>
    <h3>{{ $details['title'] }}</h3>
    <p>{{ $details['body'] }}</p>
    <p>Här är länken till sidan med din kod redan insatt.</p>
    <h3><a href="{{$details['link']}}" target="_blank">{{ $details['link'] }}</a></h3>
    <p>Stort tack för hjälpen.</p>
    <p>Mvh<br>Anton Bergenudd</p>
</body>
</html>