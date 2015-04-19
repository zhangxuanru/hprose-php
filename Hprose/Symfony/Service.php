<?php
/**********************************************************\
|                                                          |
|                          hprose                          |
|                                                          |
| Official WebSite: http://www.hprose.com/                 |
|                   http://www.hprose.org/                 |
|                                                          |
\**********************************************************/

/**********************************************************\
 *                                                        *
 * Hprose/Symfony/Server.php                              *
 *                                                        *
 * hprose symfony http server class for php 5.3+          *
 *                                                        *
 * LastModified: Apr 19, 2015                             *
 * Author: Ma Bingyao <andot@hprose.com>                  *
 *                                                        *
\**********************************************************/

namespace Hprose\Symfony {

    class Service extends \Hprose\Service {
        private $crossDomain = false;
        private $P3P = false;
        private $get = true;
        private $origins = array();
        public $onSendHeader = null;

        private function sendHeader($context) {
            if ($this->onSendHeader !== null) {
                $sendHeader = $this->onSendHeader;
                $sendHeader($context);
            }
            $context->response->headers->set('Content-Type', 'text/plain');
            if ($this->P3P) {
                $context->response->headers->set('P3P',
                        'CP="CAO DSP COR CUR ADM DEV TAI PSA PSD ' .
                        'IVAi IVDi CONi TELo OTPi OUR DELi SAMi OTRi ' .
                        'UNRi PUBi IND PHY ONL UNI PUR FIN COM NAV ' .
                        'INT DEM CNT STA POL HEA PRE GOV"');
            }
            if ($this->crossDomain) {
                if ($context->request->server->has('HTTP_ORIGIN') &&
                    $context->request->server->get('HTTP_ORIGIN') != "null") {
                    $origin = $context->request->server->get('HTTP_ORIGIN');
                    if (count($this->origins) === 0 ||
                        isset($this->origins[strtolower($origin)])) {
                        $context->response->headers->set('Access-Control-Allow-Origin', $origin);
                        $context->response->headers->set('Access-Control-Allow-Credentials', 'true');
                    }
                }
                else {
                    $context->response->headers->set('Access-Control-Allow-Origin', '*');
                }
            }
        }
        public function isCrossDomainEnabled() {
            return $this->crossDomain;
        }
        public function setCrossDomainEnabled($enable = true) {
            $this->crossDomain = $enable;
        }
        public function isP3PEnabled() {
            return $this->P3P;
        }
        public function setP3PEnabled($enable = true) {
            $this->P3P = $enable;
        }
        public function isGetEnabled() {
            return $this->get;
        }
        public function setGetEnabled($enable = true) {
            $this->get = $enable;
        }
        public function addAccessControlAllowOrigin($origin) {
            $count = count($origin);
            if (($count > 0) && ($origin[$count - 1] === "/")) {
                $origin = substr($origin, 0, -1);
            }
            $this->origins[strtolower($origin)] = true;
        }
        public function removeAccessControlAllowOrigin($origin) {
            $count = count($origin);
            if (($count > 0) && ($origin[$count - 1] === "/")) {
                $origin = substr($origin, 0, -1);
            }
            unset($this->origins[strtolower($origin)]);
        }
        public function handle($request, $response, $session) {
            $data = $request->getContent();
            $context = new \stdClass();
            $context->server = $this;
            $context->request = $request;
            $context->response = $response;
            $context->session = $session;
            $context->userdata = new \stdClass();
            $self = $this;

            set_error_handler(function($errno, $errstr, $errfile, $errline) use ($self, $context) {
                if ($self->debug) {
                    $errstr .= " in $errfile on line $errline";
                }
                $error = $self->getErrorTypeString($errno) . ": " . $errstr;
                $context->response->setContent($self->sendError($error, $context));
            }, $this->error_types);

            ob_start(function($data) use ($self, $context) {
                $match = array();
                if (preg_match('/<b>.*? error<\/b>:(.*?)<br/', $data, $match)) {
                    if ($self->debug) {
                        $error = preg_replace('/<.*?>/', '', $match[1]);
                    }
                    else {
                        $error = preg_replace('/ in <b>.*<\/b>$/', '', $match[1]);
                    }
                    $context->response->setContent($self->sendError(trim($error), $context));
                }
            });
            ob_implicit_flush(0);

            $this->sendHeader($context);
            $result = '';

            if (isset($request->server->get('REQUEST_METHOD'))) {
                if (($request->server->get('REQUEST_METHOD') == 'GET') && $this->get) {
                    $result = $this->doFunctionList($context);
                }
                elseif ($request->server->get('REQUEST_METHOD') == 'POST') {
                    $result = $this->defaultHandle($data, $context);
                }
            }
            else {
                $result = $this->doFunctionList($context);
            }
            ob_clean();
            ob_end_flush();
            $response->setContent($result);
            return $response;
        }
    }

    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\Session\Session;

    class Server extends Service {
        public function start() {
            $request = Request::createFromGlobals();
            $response = new Response();
            $session = new Session();
            $session->start();
            return $this->handle($request, $response, $session);
        }
    }
}