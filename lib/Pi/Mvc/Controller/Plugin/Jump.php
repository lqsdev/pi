<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Pi\Mvc\Controller\Plugin;

use Pi;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\InjectApplicationEventInterface;

/**
 * Jump to a page going through a transition page
 *
 * Sample code:
 *
 * ```
 *  // Jump to a direct URL
 *  $this->jump(<URI>, <Message>, <Transition time in seconds>);
 *
 *  // Jump to a routed URL
 *  $this->jump(array('route' => <route-name>,
 *      'controller' => <controller-name>), <Message>);
 * ```
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Jump extends AbstractPlugin
{
    /** @var string Namespace for session */
    protected static $sessionNamespace = 'PI_JUMP';

    /**
     * Generates a URL based on a route
     *
     * @param string|array  $params         URI or params to assemble URI
     * @param string        $message
     *      Message to display on transition page
     * @param int           $time
     *      Time to wait on transition page before directed, in seconds
     * @param bool          $allowExtenal   Allow external links
     * @return Response
     */
    public function __invoke(
        $params,
        $message = '',
        $time = 3,
        $allowExternal = false
    ) {
        $controller = $this->getController();
        if (is_array($params)) {
            $routeMatch = null;
            if (!isset($params['route'])) {
                $routeMatch = $this->getEvent()->getRouteMatch();
                $route = $routeMatch->getMatchedRouteName();
            } else {
                $route = $params['route'];
                unset($params['route']);
            }
            if (!isset($params['module'])) {
                $routeMatch = $routeMatch
                    ?: $this->getEvent()->getRouteMatch();
                $params['module'] = $routeMatch->getParam('module');
                if (!isset($params['controller'])) {
                    $params['controller'] =
                        $routeMatch->getParam('controller');
                }
            }
            $urlPlugin = $controller->plugin('url');
            $url = $urlPlugin->fromRoute($route, $params);
        } else {
            $url = $params;
            if (preg_match('/^(http[s]?:\/\/|\/\/)/i', $url)) {
                if (!$allowExternal
                    && '' !== stristr($url, Pi::url('www'), true)
                ) {
                    $url = Pi::url('www');
                }
            } elseif ('/' != $url[0]) {
                $url = Pi::url('www') . '/' . $url;
            }
        }

        $jumpParams = array(
            'time'      => $time,
            'message'   => $message,
            'url'       => $url,
        );
        $_SESSION[static::$sessionNamespace] = $jumpParams;

        $this->controller->view()->setTemplate(false);
        $response = $controller->plugin('redirect')->toRoute('jump');
        if ($response instanceof Response) {
            $response->send();
        }

        return $response;
        //$response->send();
        //exit();
    }

    /**
     * Get the event
     *
     * @return MvcEvent
     * @throws \DomainException if unable to find event
     */
    protected function getEvent()
    {
        if (isset($this->event)) {
            return $this->event;
        }

        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new \DomainException(
                'Controller plugin requires a controller that implements'
                . ' InjectApplicationEventInterface'
            );
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent) {
            $params = $event->getParams();
            $event  = new MvcEvent();
            $event->setParams($params);
        }
        $this->event = $event;

        return $this->event;
    }
}
