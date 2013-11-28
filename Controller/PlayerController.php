<?php

namespace Innova\PathBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

use Innova\PathBundle\Entity\Path;
use Innova\PathBundle\Entity\Step;
use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Player controller
 * @author Innovalangues <contact@innovalangues.net>
 * 
 * @Route(
 *      "",
 *      name="innova_path_player",
 *      service="innova.controller.path_player"
 * )
 */
class PlayerController extends ContainerAware
{
    /**
     * Display path player
     * @param  Path $path
     * @return array
     * 
     * @Route(
     *      "workspace/{workspaceId}/path/{pathId}/step/{stepId}",
     *      name="innova_path_player_index",
     *      options={"expose" = true}
     * )
     * @ParamConverter("workspace", class="ClarolineCoreBundle:Workspace\AbstractWorkspace", options={"mapping": {"workspaceId": "id"}})
     * @ParamConverter("path", class="InnovaPathBundle:Path", options={"mapping": {"pathId": "id"}})
     * @ParamConverter("currentStep", class="InnovaPathBundle:Step", options={"mapping": {"stepId": "id"}})
     * @Method("GET")
     * @Template("InnovaPathBundle:Player:main.html.twig")
     */
    public function displayAction(AbstractWorkspace $workspace, Path $path, Step $currentStep)
    {

        return array (
            'workspace' => $workspace,
            'path' => $path,
            'currentStep' => $currentStep
        );
    }
    
    /**
     * @Method("GET")
     * @Template("InnovaPathBundle:Player:components/breadcrumbs.html.twig")
     */
    public function displayBreadcrumbsAction(AbstractWorkspace $workspace, Path $path, Step $currentStep)
    {
        $session = $this->container->get('request')->getSession();
        $history = $session->get('history');
        
        if(!array_key_exists($path->getId(), $history)){
            $history[$path->getId()] = array();
        }

        foreach($history[$path->getId()] as $order => $stepId){
            if(array_key_exists($currentStep->getId(), $stepId)){
                unset($history[$path->getId()][$order]);
            }
        }
        array_unshift($history[$path->getId()], array($currentStep->getId() => $currentStep->getName()));
        $session->set('history', $history);



        return array (
            'workspace' => $workspace,
            'path' => $path,
            'currentStep' => $currentStep,
        );
    }
    
    /**
     * @Method("GET")
     * @Template("InnovaPathBundle:Player:components/resources.html.twig")
     */
    public function displayResourcesAction(Step $currentStep)
    {
        return array (
            'currentStep' => $currentStep,
        );
    }
    
    /**
     * @Method("GET")
     * @Template("InnovaPathBundle:Player:components/squares-browser.html.twig")
     */
    public function displaySquaresBrowserAction(AbstractWorkspace $workspace, Path $path, Step $currentStep)
    {
        return array (
            'workspace' => $workspace,
            'path' => $path,
            'currentStep' => $currentStep,
        );
    }
    
    /**
     * @Method("GET")
     * @Template("InnovaPathBundle:Player:components/tree-browser.html.twig")
     */
    public function displayTreeBrowserAction(Path $path, Step $currentStep)
    {
        return array (
            'path' => $path,
            'currentStep' => $currentStep,
        );
    }
    
    /**
     * @Method("GET")
     * @Template("InnovaPathBundle:Player:components/current-step.html.twig")
     */
    public function displayCurrentStepAction(Step $currentStep)
    {
        return array (
            'currentStep' => $currentStep,
        );
    }
}