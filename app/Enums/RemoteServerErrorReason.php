<?php

namespace App\Enums;

enum RemoteServerErrorReason: string
{
    case Unauthorized = 'unauthorized';
    case ApiError = 'api_error';
    case Timeout = 'timeout';
    case ConnectionFailed = 'failed';
    case InvalidResponse = 'invalid_response';
}
