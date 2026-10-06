<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Every /admin-next path answers 200 (Vue Router shows its own 404), so keep it out of search engines. --}}
        <meta name="robots" content="noindex">

        <title>Panel sklepu</title>

        @fonts

        @vite('resources/js/admin/main.ts')
    </head>
    <body>
        {{-- Vue panel; Vue Router handles every /admin-next path, data and permissions come from /api. --}}
        <div id="app"></div>
    </body>
</html>
