<?php

namespace AtKafasi\LogTracing;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use App\Exceptions\Handler;
use Throwable;
use Illuminate\Support\Facades\Http;
class LogTracingServiceProvider extends ServiceProvider
{
    public function boot(){
        $this->publishes([
            __DIR__ . '/../config/log-tracing.php' => config_path('log-tracing.php')
        ] , 'at-kafasi-log-tracing-config');
    }
    public function register()
    {
        $ExceptionHandler = $this->app->make(ExceptionHandler::class);
        $Handler = $this->app->make(Handler::class);
        $ExceptionHandler->reportable(function (Throwable $e) use ($Handler) {
            if ($Handler->shouldReport($e)) {
                if (!empty($e)) {
                    $exception = $e;
                    if (is_object($exception)) {
                        if (get_class($exception) === \Exception::class
                            || get_class($exception) === \Throwable::class
                            || is_subclass_of($exception, \Exception::class)
                            || is_subclass_of($exception, \Throwable::class)
                            || strpos(get_class($exception), "Exception") !== false
                            || strpos(get_class($exception), "Throwable") !== false) {

                            $newexception = [];

                            if (method_exists($exception, 'getMessage')) {
                                $newexception['message'] = $exception->getMessage();
                            }
                            if (method_exists($exception, 'getCode')) {
                                $newexception['code'] = $exception->getCode();
                            }
                            if (method_exists($exception, 'getFile')) {
                                $newexception['file'] = $exception->getFile();
                            }
                            if (method_exists($exception, 'getLine')) {
                                $newexception['line'] = $exception->getLine();
                            }
                            if ($exception->getTrace() && method_exists($exception, 'getTraceAsString')) {
                                $newexception['trace'] = $exception->getTraceAsString();
                            }
                            if (method_exists($exception, 'getSeverity')) {
                                $newexception['severity'] = $exception->getSeverity();
                            }

                            $newexception['services'] = config('log-tracing.log.service_name');
                            $newexception['unix_time'] = time();

                            $context = $newexception;
                        }
                    }
                }
                
                $response = Http::withHeaders([
                    'Authorization' => config('log-tracing.log.secret'),
                ])->post(config('log-tracing.log.base_uri').'/create/log', [
                    'error' => $context,
                ]);
            }
        });
    }
}
