<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Instalace')</title>
    {{-- Záměrně inline: průvodce běží i na webu, kde ještě nic není sestavené. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 2.5rem 1.25rem 4rem;
            background: #f6f6f5;
            color: #1c1917;
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .wrap { max-width: 46rem; margin: 0 auto; }
        .card {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
        }
        h1 { font-size: 1.6rem; margin: 0 0 .35rem; letter-spacing: -.02em; }
        h2 { font-size: 1.05rem; margin: 0 0 1rem; }
        .lead { color: #57534e; margin: 0 0 2rem; }
        .brand { display: inline-block; background: #db4b24; color: #fff; font-weight: 700;
                 padding: .2rem .55rem; border-radius: 6px; font-size: .8rem; letter-spacing: .06em; }
        label { display: block; font-weight: 600; font-size: .875rem; margin-bottom: .3rem; }
        .hint { font-weight: 400; color: #78716c; font-size: .8rem; }
        input[type=text], input[type=password], input[type=url] {
            width: 100%; padding: .6rem .7rem; border: 1px solid #d6d3d1;
            border-radius: 8px; font: inherit; font-size: .95rem; background: #fff;
        }
        input:focus { outline: 2px solid #db4b24; outline-offset: 1px; border-color: #db4b24; }
        .field { margin-bottom: 1.1rem; }
        .row { display: flex; gap: 1rem; }
        .row > * { flex: 1; }
        button {
            background: #db4b24; color: #fff; border: 0; border-radius: 8px;
            padding: .75rem 1.4rem; font: inherit; font-weight: 600; cursor: pointer;
        }
        button:hover { background: #c03f1c; }
        ul.checks { list-style: none; margin: 0; padding: 0; font-size: .9rem; }
        ul.checks li { display: flex; justify-content: space-between; gap: 1rem;
                       padding: .35rem 0; border-bottom: 1px solid #f5f5f4; }
        ul.checks li:last-child { border-bottom: 0; }
        .ok { color: #15803d; font-weight: 600; }
        .bad { color: #b91c1c; font-weight: 600; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
                 border-radius: 8px; padding: 1rem 1.15rem; margin-bottom: 1.25rem; }
        .alert ul { margin: .5rem 0 0; padding-left: 1.1rem; }
        .success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        pre { background: #1c1917; color: #e7e5e4; padding: .8rem 1rem; border-radius: 8px;
              overflow-x: auto; font-size: .78rem; margin: .5rem 0 0; }
        .cta { display: inline-block; background: #db4b24; color: #fff; text-decoration: none;
               padding: .75rem 1.4rem; border-radius: 8px; font-weight: 600; }
        .note { font-size: .85rem; color: #78716c; }
        @media (max-width: 34rem) { .row { display: block; } .row > * { margin-bottom: 1.1rem; } }
    </style>
</head>
<body>
<div class="wrap">
    @yield('content')
</div>
</body>
</html>
