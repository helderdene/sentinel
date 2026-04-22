<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            line-height: 1.7;
            max-width: 680px;
            margin: 0 auto;
            padding: 40px;
            color: #111;
        }
        h1 {
            font-size: 24pt;
            margin-bottom: 0.5em;
            border-bottom: 2px solid #111;
            padding-bottom: 0.3em;
        }
        h2 {
            font-size: 16pt;
            margin-top: 1.5em;
        }
        h3 {
            font-size: 12pt;
            margin-top: 1em;
        }
        p, li {
            font-size: 11pt;
        }
        code {
            background: #f4f4f4;
            padding: 2px 4px;
            border-radius: 2px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 1em 0;
        }
        table th, table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            font-size: 10pt;
            text-align: left;
        }
        table th {
            background: #f4f4f4;
        }
        blockquote {
            border-left: 3px solid #888;
            padding-left: 12px;
            color: #444;
            margin: 1em 0;
        }
    </style>
</head>
<body>
    {!! $content !!}
</body>
</html>
