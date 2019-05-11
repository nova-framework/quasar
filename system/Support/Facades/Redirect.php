<?php

namespace System\Support\Facades;

use System\Response as HttpResponse;


class Redirect
{

    public static function to($path, $status = 302, array $headers = array())
    {
        $url = site_url($path);

        return static::createRedirectResponse($url, $status, $headers);
    }

    protected static function createRedirectResponse($url, $status, $headers)
    {
        $content = '
<html>
<body onload="redirect_to(\'' .$url .'\');"></body>
<script type="text/javascript">function redirect_to(url) { window.location.href = url; }</script>
</body>
</html>';

        $headers['Location'] = $url;

        return new HttpResponse($content, $status, $headers);
    }
}
