<?php

namespace Bolt\Controller\Async;

use Bolt\Filesystem\Handler\FileInterface;
use Bolt\Response\TemplateResponse;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Async controller for Stack async routes.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 * @author Carson Full <carsonfull@gmail.com>
 */
class Stack extends AsyncBase
{
    protected function addRoutes(ControllerCollection $c)
    {
        $c->post('/stack/add', 'add')
            ->assert('filename', '.*')
            ->bind('stack/add')
        ;

        $c->get('/stack/show', 'show')
            ->bind('stack/show')
        ;

        $c->get('/stack/panel/{fileName}', 'panelItem')
            ->bind('stack/panelItem')
        ;

        $c->get('/stack/list/{fileName}', 'listItem')
            ->bind('stack/listItem')
        ;
    }

    /**
     * Add a file to the user's stack.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function add(Request $request)
    {
        $fileName = $request->request->get('filename');
        $stack = $this->app['stack'];

        /** @var FileInterface|null $removed */
        $file = $stack->add($fileName, $removed);
        $fileName = $file->getFullPath();

        $panel = $this->makeItemSubRequest($request, 'stack/panelItem', $fileName);
        $list = $this->makeItemSubRequest($request, 'stack/listItem', $fileName);

        $type = $file->getType();
        $type = !in_array($type, ['image', 'document']) ? 'other' : $type;

        return $this->json([
            'type'    => $type,
            'removed' => $removed ? $removed->getFullPath() : null,
            'panel'   => $panel->getContent(),
            'list'    => $list->getContent(),
        ]);
    }

    /**
     * Render a Stack panel file item.
     *
     * @param string $fileName
     *
     * @return TemplateResponse|\Bolt\Response\TemplateView
     */
    public function panelItem($fileName)
    {
        /** @var FileInterface|null $file */
        $file = $this->filesystem()->getFile(urldecode($fileName));

        return $this->render('@bolt/components/stack/panel-item.twig', ['file' => $file]);

    }

    /**
     * Render a Stack list file item.
     *
     * @param string $fileName
     *
     * @return TemplateResponse|\Bolt\Response\TemplateView
     */
    public function listItem($fileName)
    {
        /** @var FileInterface|null $file */
        $file = $this->filesystem()->getFile(urldecode($fileName));

        return $this->render('@bolt/components/stack/list-item.twig', ['file' => $file]);
    }

    /**
     * Render a user's current stack.
     *
     * @param Request $request
     *
     * @return TemplateResponse
     */
    public function show(Request $request)
    {
        $count = $request->query->get('count', \Bolt\Stack::MAX_ITEMS);
        $options = $request->query->get('options');

        if ($options === 'ck') {
            $template = '@bolt/components/stack/ck.twig';
        } elseif ($options === 'list') {
            $template = '@bolt/components/stack/list.twig';
        } else {
            $template = '@bolt/components/stack/panel.twig';
        }

        $context = [
            'count'     => $count,
            'filetypes' => $this->getOption('general/accept_file_types'),
            'namespace' => $this->app['upload.namespace'],
            'canUpload' => $this->isAllowed('files:uploads'),
        ];

        return $this->render($template, ['context' => $context]);
    }

    /**
     * Perform, and return, a sub request for a rendered HTML stack item.
     *
     * @param Request $request
     * @param string  $routeName
     * @param string  $fileName
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function makeItemSubRequest(Request $request, $routeName, $fileName)
    {
        $panelUri = $this->generateUrl($routeName, ['fileName' => urlencode($fileName)]);
        $subRequest = Request::create($panelUri, Request::METHOD_GET, [], $request->cookies->all(), [], $request->server->all());
        $subRequest->setSession($request->getSession());

        return $this->app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
    }
}
