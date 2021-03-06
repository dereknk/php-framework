<?php

namespace Core;

use App;
use Core\Cipher\Cipher;
use Core\Cipher\CipherInterface;
use Core\Exception\AppException;
use Core\Http\Cookie;
use Core\Http\Request;
use Core\Http\Response;
use Psr\Http\Message\UriInterface;

/**
 * 控制器基类
 *
 * @author lisijie <lsj86@qq.com>
 * @package Core
 */
class Controller extends Component
{
    /**
     * 输出的数据
     * @var array
     */
    private $data = [];

    /**
     * 默认动作
     * @var string
     */
    protected $defaultAction = 'index';

    /**
     * 请求对象
     * @var \Core\Http\Request
     */
    protected $request;

    /**
     * 输出对象
     * @var \Core\Http\Response
     */
    protected $response;

    /**
     * 提示消息的模板文件
     *
     * @var string
     */
    protected $messageTemplate = 'message';

    /**
     * 是否允许jsonp
     * @var bool
     */
    protected $jsonpEnabled = false;

    /**
     * jsonp回调参数名
     * @var string
     */
    protected $jsonCallback = 'jsoncallback';

    /**
     * 加密解密
     * @var CipherInterface
     */
    protected $cipher;

    /**
     * 构造方法，不可重写
     * 子类可通过重写init()方法完成初始化
     *
     * @param Request $request 请求对象
     * @param Response $response 输出对象
     */
    public final function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * 控制器初始化方法，执行初始化操作
     */
    public function init()
    {

    }

    /**
     * 获取服务器环境变量
     *
     * @param string $name 名称
     * @param null $default
     * @param bool $applyFilter
     * @return string
     */
    protected function getServer($name, $default = null, $applyFilter = true)
    {
        return $this->request->getServerParam($name, $default, $applyFilter);
    }

    /**
     * 获取GET/POST值
     *
     * @param string $name
     * @param mixed $default
     * @param bool $applyFilter
     * @return mixed
     */
    protected function get($name, $default = null, $applyFilter = true)
    {
        if (null === ($value = $this->request->getQueryParam($name, null, $applyFilter))) {
            $value = $this->request->getPostParam($name, $default, $applyFilter);
        }
        return $value;
    }

    /**
     * 获取GET值
     *
     * @param string $name
     * @param mixed $default
     * @param bool $applyFilter
     * @return mixed|null
     */
    protected function getQuery($name = null, $default = null, $applyFilter = true)
    {
        if (null === $name) {
            return $this->request->getQueryParams($applyFilter);
        }
        return $this->request->getQueryParam($name, $default, $applyFilter);
    }

    /**
     * 获取POST值
     *
     * @param string $name
     * @param mixed $default
     * @param bool $applyFilter
     * @return mixed|null
     */
    protected function getPost($name = null, $default = null, $applyFilter = true)
    {
        if (null === $name) {
            return $this->request->getParsedBody($applyFilter);
        }
        return $this->request->getPostParam($name, $default, $applyFilter);
    }

    /**
     * 获取Cookie值
     *
     * @param string $name
     * @param bool $isSecure 是否加密Cookie
     * @param null $secret 密钥
     * @param bool $applyFilter
     * @return mixed|null
     */
    protected function getCookie($name, $isSecure = false, $secret = null, $applyFilter = true)
    {
        $value = $this->request->getCookieParam($name, null, $applyFilter);
        if ($isSecure && $value != null) {
            if ($secret === null) {
                $secret = App::config()->get('app', 'secret_key');
            }
            if (empty($secret)) {
                throw new \RuntimeException("请先到app配置文件设置密钥: secret_key");
            }
            $value = $this->getCipher()->decrypt($value, $secret);
        }
        return $value;
    }

    /**
     * 设置Cookie
     *
     * @param Cookie $cookie
     * @param bool $secure 是否加密
     * @param null $secret 指定密钥
     */
    protected function setCookie(Cookie $cookie, $secure = false, $secret = null)
    {
        if ($secure) {
            if ($secret === null) {
                $secret = App::config()->get('app', 'secret_key');
            }
            if (empty($secret)) {
                throw new \RuntimeException("请先到app配置文件设置密钥: secret_key");
            }
            $value = $this->getCipher()->encrypt($cookie->getValue(), $secret);
            $cookie->setValue($value);
        }
        $this->response = $this->response->withCookie($cookie);
    }

    /**
     * @return Cipher|CipherInterface
     */
    protected function getCipher()
    {
        if (!$this->cipher) {
            $this->cipher = Cipher::createSimple();
        }
        return $this->cipher;
    }

    /**
     * 添加一个输出变量
     *
     * @param mixed $name 变量
     * @param mixed $value 变量的值
     */
    protected function assign($name, $value = null)
    {
        if (is_array($name)) {
            $this->data = array_merge($this->data, $name);
        } else {
            $this->data[$name] = $value;
        }
    }

    /**
     * 返回要输出的数据
     *
     * @return array
     */
    protected function getData()
    {
        return $this->data;
    }

    /**
     * 设置视图的布局模板文件
     *
     * @param $filename
     */
    protected function setLayout($filename)
    {
        App::view()->setLayout($filename);
    }

    /**
     * 设置视图的子布局模板文件
     *
     * @param $name
     * @param $filename
     */
    protected function setLayoutSection($name, $filename)
    {
        App::view()->setLayoutSection($name, $filename);
    }

    /**
     * 获取请求来源地址
     *
     * @return string
     */
    protected function getReferrer()
    {
        if ($this->getServer('HTTP_REFERER') == '' ||
            strpos($this->getServer('HTTP_REFERER'), $this->getServer('HTTP_HOST')) === FALSE
        ) {
            $refer = '';
        } else {
            $refer = $this->getServer('HTTP_REFERER');
            if (strpos($refer, '#') !== false) {
                $refer = substr($refer, 0, strpos($refer, '#'));
            }
        }

        return $refer;
    }

    /**
     * URL跳转
     *
     * @param string|UriInterface $url 目的地址
     * @param int $status 状态码
     * @return Response 输出对象
     */
    protected function redirect($url, $status = 302)
    {
        return $this->response->withHeader('Location', $url)->withStatus($status);
    }

    /**
     * 跳转回首页
     *
     * @return Response
     */
    protected function goHome()
    {
        return $this->redirect($this->request->getBaseUrl() ?: '/');
    }

    /**
     * 跳转到来源页面
     *
     * 优先级：
     * 1. URL中的refer参数
     * 2. 存在名为refer的cookie
     * 3. 使用HTTP_REFERER
     *
     * @param string $defaultUrl 默认URL
     * @param bool $verifyHost 是否检查域名
     * @return Response
     */
    protected function goBack($defaultUrl = '', $verifyHost = true)
    {
        $url = $this->get('refer');
        if (empty($url)) {
            $url = $this->getCookie('refer');
        }
        if (empty($url)) {
            $url = $this->getReferrer();
        }
        if (empty($url)) {
            $url = $defaultUrl;
        }
        if (strpos($url, '//') === false) {
            $url = '/' . ltrim($url, '/');
        } elseif ($verifyHost) {
            $host = parse_url($url, PHP_URL_HOST);
            if (empty($host) || $host != $this->request->getUri()->getHost()) {
                $url = '';
            }
        }
        // 如果没有来源页面，跳转到首页
        if (empty($url)) {
            return $this->goHome();
        }
        return $this->redirect($url);
    }

    /**
     * 刷新当前页面
     *
     * @param string $anchor 附加url hash
     * @return mixed
     */
    protected function refresh($anchor = '')
    {
        $uri = $this->request->getUri();
        if ($anchor) {
            $uri = $uri->withFragment($anchor);
        }
        return $this->redirect($uri);
    }

    /**
     * JSON编码
     *
     * @param $data
     * @return mixed|string
     */
    protected function jsonEncode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 输出JSON格式
     *
     * @param array $data 输出的数据，默认使用assign的数据
     * @return Response
     */
    protected function serveJSON(array $data = [])
    {
        if (empty($data)) {
            $data = $this->data;
        }
        $content = $this->jsonEncode($data);
        $callback = $this->get($this->jsonCallback);
        if ($this->jsonpEnabled && $callback != '') {
            $func = $callback{0} == '?' ? '' : $callback;
            $content = "{$func}($content)";
        }

        $response = $this->response->withHeader('Content-Type', "application/json; charset=" . CHARSET);
        $response->getBody()->write($content);
        return $response;
    }

    /**
     * 提示消息
     *
     * @param string $message 提示消息
     * @param int $code 消息号
     * @param string $jumpUrl 跳转地址
     * @return Response 输出对象
     */
    public function message($message, $code = MSG_ERR, $jumpUrl = NULL)
    {
        $data = [
            'code' => $code,
            'msg' => $message,
            'jumpUrl' => $jumpUrl,
        ];
        $this->assign($data);
        $content = App::view()->render($this->messageTemplate, $this->data);
        $this->response->getBody()->write($content);
        return $this->response;
    }

    /**
     * 渲染模板并返回Response对象
     *
     * @param string $filename
     * @param array $data
     * @return Response
     */
    public function render($filename = '', $data = [])
    {
        if (empty($filename)) {
            $filename = CUR_ROUTE;
        }
        $data = array_merge($this->data, $data);
        $content = App::view()->render($filename, $data);
        $this->response->getBody()->write($content);
        return $this->response;
    }

    /**
     * 执行控制器方法
     *
     * @param string $actionName 方法名
     * @param array $params 参数列表
     * @return Response|mixed
     * @throws AppException
     */
    public function execute($actionName, $params = [])
    {
        if (empty($actionName)) {
            $actionName = $this->defaultAction;
        }
        $actionName .= 'Action';
        if (!method_exists($this, $actionName)) {
            throw new \BadMethodCallException("方法不存在: " . get_class($this) . "::{$actionName}");
        }

        $method = new \ReflectionMethod($this, $actionName);
        if (!$method->isPublic()) {
            throw new \BadMethodCallException("调用非公有方法: " . get_class($this) . "::{$actionName}");
        }

        $args = [];
        $methodParams = $method->getParameters();
        if (!empty($methodParams)) {
            foreach ($methodParams as $p) {
                $default = $p->isOptional() ? $p->getDefaultValue() : null;
                $value = array_key_exists($p->getName(), $params) ? $params[$p->getName()] : $default;
                if (null === $value && !$p->isOptional()) {
                    throw new AppException('缺少请求参数:' . $p->getName());
                }
                $args[] = $value;
            }
        }
        $result = $method->invokeArgs($this, $args);
        if ($result instanceof Response) {
            return $result;
        } elseif (null !== $result) {
            $this->response->getBody()->write((string)$result);
        }
        return $this->response;
    }

    /**
     * 控制器内部变量允许外部读取
     *
     * @param string $name
     * @return mixed
     * @throws \ErrorException
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        throw new \ErrorException(sprintf('Undefined property: %s::$%s', static::class, $name));
    }

}
