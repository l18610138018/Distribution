<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Security\Voter;

use Claroline\CoreBundle\Entity\Resource\AbstractResource;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Library\Security\Collection\ResourceCollection;
use Claroline\CoreBundle\Library\Security\Utilities;
use Claroline\CoreBundle\Manager\Resource\MaskManager;
use Claroline\CoreBundle\Manager\Resource\ResourceRestrictionsManager;
use Claroline\CoreBundle\Manager\Resource\RightsManager;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Manager\WorkspaceManager;
use Claroline\CoreBundle\Repository\ResourceRightsRepository;
use Doctrine\ORM\EntityManager;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * This voter is involved in access decisions for AbstractResource instances.
 *
 * Please note that the 'ADMINISTRATE' perm does a lot of things and it's sadly not always
 *
 * @DI\Service
 * @DI\Tag("security.voter")
 */
class ResourceVoter implements VoterInterface
{
    private $em;
    /** @var ResourceRightsRepository */
    private $repository;
    private $translator;
    private $specialActions;
    private $ut;
    private $maskManager;
    private $resourceManager;
    private $workspaceManager;
    private $rightsManager;
    private $restrictionsManager;

    /**
     * ResourceVoter constructor.
     *
     * @DI\InjectParams({
     *     "em"                  = @DI\Inject("doctrine.orm.entity_manager"),
     *     "translator"          = @DI\Inject("translator"),
     *     "ut"                  = @DI\Inject("claroline.security.utilities"),
     *     "maskManager"         = @DI\Inject("claroline.manager.mask_manager"),
     *     "resourceManager"     = @DI\Inject("claroline.manager.resource_manager"),
     *     "workspaceManager"    = @DI\Inject("claroline.manager.workspace_manager"),
     *     "rightsManager"       = @DI\Inject("claroline.manager.rights_manager"),
     *     "restrictionsManager" = @DI\Inject("claroline.manager.resource_restrictions")
     * })
     *
     * @param EntityManager               $em
     * @param TranslatorInterface         $translator
     * @param Utilities                   $ut
     * @param MaskManager                 $maskManager
     * @param ResourceManager             $resourceManager
     * @param WorkspaceManager            $workspaceManager
     * @param RightsManager               $rightsManager
     * @param ResourceRestrictionsManager $restrictionsManager
     */
    public function __construct(
        EntityManager $em,
        TranslatorInterface $translator,
        Utilities $ut,
        MaskManager $maskManager,
        ResourceManager $resourceManager,
        WorkspaceManager $workspaceManager,
        RightsManager $rightsManager,
        ResourceRestrictionsManager $restrictionsManager
    ) {
        $this->em = $em;
        $this->repository = $em->getRepository('ClarolineCoreBundle:Resource\ResourceRights');
        $this->translator = $translator;
        $this->specialActions = ['move', 'create', 'copy'];
        $this->ut = $ut;
        $this->maskManager = $maskManager;
        $this->resourceManager = $resourceManager;
        $this->workspaceManager = $workspaceManager;
        $this->rightsManager = $rightsManager;
        $this->restrictionsManager = $restrictionsManager;
    }

    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $classes = $this->supportsClass($object);
        $supports = false;

        foreach ($classes as $class) {
            if ($object instanceof $class) {
                $supports = true;
            }
        }

        if (!$supports) {
            return false;
        }

        if ($this->isAdmin($object)) {
            return VoterInterface::ACCESS_GRANTED;
        }

        if (!$this->validateAccesses($object)) {
            return VoterInterface::ACCESS_DENIED;
        }

        $object = $object instanceof AbstractResource ? $object->getResourceNode() : $object;

        if ($object instanceof ResourceCollection) {
            $errors = [];
            if ('create' === strtolower($attributes[0])) {
                if ($object->getResources()[0]) {
                    foreach ($object->getResources() as $resource) {
                        $errors = array_merge(
                            $errors,
                            $this->checkCreation($object->getAttribute('type'), $resource, $token)
                        );
                    }
                } else {
                    return VoterInterface::ACCESS_GRANTED;
                }
            } elseif ('move' === strtolower($attributes[0])) {
                $errors = array_merge(
                    $errors,
                    $this->checkMove($object->getAttribute('parent'), $object->getResources(), $token)
                );
            } elseif ('copy' === strtolower($attributes[0])) {
                $errors = array_merge(
                    $errors,
                    $this->checkCopy($object->getAttribute('parent'), $object->getResources(), $token)
                );
            } else {
                $errors = array_merge(
                    $errors,
                    $this->checkAction(strtolower($attributes[0]), $object->getResources(), $token)
                );
            }

            if (0 === count($errors)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            $errors = array_unique($errors);
            $object->setErrors($errors);

            return VoterInterface::ACCESS_DENIED;
        } elseif ($object instanceof ResourceNode) {
            if (in_array($attributes[0], $this->specialActions)) {
                throw new \Exception('A ResourceCollection class must be used for this action.');
            }

            $errors = $this->checkAction($attributes[0], [$object], $token);

            return 0 === count($errors) && $object->isActive() ?
                VoterInterface::ACCESS_GRANTED :
                VoterInterface::ACCESS_DENIED;
        }

        return VoterInterface::ACCESS_ABSTAIN;
    }

    public function supportsAttribute($attribute)
    {
        return true;
    }

    public function supportsClass($class)
    {
        return [
            'Claroline\CoreBundle\Entity\Resource\AbstractResource',
            'Claroline\CoreBundle\Entity\Resource\ResourceNode',
            'Claroline\CoreBundle\Library\Security\Collection\ResourceCollection',
        ];
    }

    /**
     * Checks if the resourceType name $resourceType is in the
     * $rightsCreation array.
     *
     * @param array  $rightsCreation
     * @param string $resourceType
     *
     * @return bool
     */
    private function canCreate(array $rightsCreation, $resourceType)
    {
        foreach ($rightsCreation as $item) {
            if ($item['name'] === $resourceType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string         $action
     * @param ResourceNode[] $nodes
     * @param TokenInterface $token
     *
     * @return array
     *
     * @throws \Exception
     */
    private function checkAction($action, array $nodes, TokenInterface $token)
    {
        $haveSameWorkspace = true;
        $ws = $nodes[0]->getWorkspace();

        foreach ($nodes as $node) {
            if ($node->getWorkspace() !== $ws) {
                $haveSameWorkspace = false;
                break;
            }
        }

        //the workspace manager he can do w/e he wants
        if ($haveSameWorkspace && $ws && $this->isWorkspaceManager($ws, $token)) {
            return [];
        }

        //the resource creator can do w/e he wants
        $timesCreator = 0;

        foreach ($nodes as $node) {
            if ($node->getCreator() === $token->getUser()) {
                ++$timesCreator;
            }
        }

        //but it only work if he's not usurping a workspace role to see if everything is good
        if ($timesCreator === count($nodes) && !$this->isUsurpatingWorkspaceRole($token)) {
            return [];
        }

        //check if the action is possible on the node
        $errors = [];
        $action = strtolower($action);

        foreach ($nodes as $node) {
            if ($node->isActive()) {
                $mask = $this->repository->findMaximumRights($this->ut->getRoles($token), $node);
                $type = $node->getResourceType();
                $decoder = $this->maskManager->getDecoder($type, $action);
                // Test if user can administrate
                $adminDecoder = $this->maskManager->getDecoder($type, 'administrate');
                $canAdministrate = $adminDecoder ? (0 !== ($mask & $adminDecoder->getValue())) : false;

                // If user can administrate OR resource is open then check action
                if ($canAdministrate ||
                    ($this->restrictionsManager->isStarted($node) &&
                     !$this->restrictionsManager->isEnded($node) &&
                    $node->isPublished())) {
                    //gotta check
                    if (!$decoder) {
                        return ['The permission '.$action.' does not exists for the type '.$type->getName()];
                    }

                    $grant = $decoder ? $mask & $decoder->getValue() : 0;

                    if ($decoder && 0 === $grant) {
                        $errors[] = $this->getRoleActionDeniedMessage($action, $node->getPathForDisplay());
                    }
                } else {
                    $errors[] = $this->getRoleActionDeniedMessage($action, $node->getPathForDisplay());
                }
            } else {
                $errors[] = $this->getRoleActionDeniedMessage($action, $node->getPathForDisplay());
            }
        }

        return $errors;
    }

    /**
     * Checks if a resource whose type is $type
     * can be created in the directory $resource by the $token.
     *
     * @param $type
     * @param ResourceNode   $node
     * @param TokenInterface $token
     *
     * @return array
     */
    private function checkCreation($type, ResourceNode $node, TokenInterface $token)
    {
        if (null === $type) {
            return [];
        }

        $errors = [];

        //even the workspace manager can't break the file limit.
        $workspace = $node->getWorkspace();
        $isLimitExceeded = $this->resourceManager
            ->checkResourceLimitExceeded($workspace);

        if ($isLimitExceeded) {
            $currentCount = $this->workspaceManager->countResources($workspace);
            $errors[] = $this->translator
                ->trans(
                    'resource_limit_exceeded',
                    ['%current%' => $currentCount, '%max%' => $workspace->getMaxUploadResources()],
                    'platform'
                );
        }

        //if I am the manager, I can do whatever I want
        if ($this->isWorkspaceManager($workspace, $token)) {
            return $errors;
        }

        //otherwise we need to check
        $rightsCreation = $this->repository->findCreationRights($this->ut->getRoles($token), $node);

        if (!$this->canCreate($rightsCreation, $type)) {
            $errors[] = $this->translator
                ->trans(
                    'resource_creation_wrong_type',
                    [
                        '%path%' => $node->getPathForDisplay(),
                        '%type%' => $this->translator->trans(
                            strtolower($type),
                            [],
                            'resource'
                        ),
                    ],
                    'platform'
                );
        }

        return $errors;
    }

    /**
     * Checks if the array of resources can be moved to the resource $parent
     * by the $token.
     *
     * @param ResourceNode   $parent
     * @param array          $nodes
     * @param TokenInterface $token
     *
     * @return array
     */
    private function checkMove(ResourceNode $parent, $nodes, TokenInterface $token)
    {
        $errors = [];

        //first I need to know if I can create
        foreach ($nodes as $node) {
            $type = $node->getResourceType()->getName();
            $errors = array_merge($errors, $this->checkCreation($type, $parent, $token));
        }

        //then I need to know if I can copy
        $errors = array_merge($errors, $this->checkCopy($parent, $nodes, $token));

        //and finally I need to know if I can delete
        $errors = array_merge($errors, $this->checkAction('DELETE', $nodes, $token));

        return $errors;
    }

    /**
     * Checks if the array of resources can be copied to the resource $parent
     * by the $token.
     *
     * @param ResourceNode   $parent
     * @param ResourceNode[] $nodes
     * @param TokenInterface $token
     *
     * @return array
     */
    private function checkCopy(ResourceNode $parent, array $nodes, TokenInterface $token)
    {
        //first I need to know if I can create what I want in the parent directory
        $errors = [];

        foreach ($nodes as $node) {
            $type = $node->getResourceType()->getName();
            $errors = array_merge($errors, $this->checkCreation($type, $parent, $token));
        }

        //then we need to know if we can copy
        $errors = array_merge($errors, $this->checkAction('COPY', $nodes, $token));

        return $errors;
    }

    public function getRoleActionDeniedMessage($action, $path)
    {
        return $this->translator
            ->trans(
                'resource_action_denied_message',
                [
                    '%path%' => $path,
                    '%action%' => $action,
                ],
                'platform'
            );
    }

    private function isWorkspaceManager(Workspace $workspace, TokenInterface $token)
    {
        $managerRoleName = 'ROLE_WS_MANAGER_'.$workspace->getGuid();

        return in_array($managerRoleName, $this->ut->getRoles($token)) ? true : false;
    }

    private function isUsurpatingWorkspaceRole(TokenInterface $token)
    {
        foreach ($token->getRoles() as $role) {
            if ('ROLE_USURPATE_WORKSPACE_ROLE' === $role->getRole()) {
                return true;
            }
        }

        return false;
    }

    private function validateAccesses($object)
    {
        if ($object instanceof ResourceNode) {
            $nodes = [$object];
        } else {
            $nodes = $object instanceof AbstractResource ? [$object->getResourceNode()] : $object->getResources();
        }

        /** @var ResourceNode $node */
        foreach ($nodes as $node) {
            if (!$this->restrictionsManager->isUnlocked($node)
                || !$this->restrictionsManager->isIpAuthorized($node)) {
                return false;
            }
        }

        return true;
    }

    private function isAdmin($object)
    {
        if ($object instanceof ResourceNode) {
            $nodes = [$object];
        } else {
            $nodes = $object instanceof AbstractResource ? [$object->getResourceNode()] : $object->getResources();
        }

        foreach ($nodes as $node) {
            if ($node && !$this->rightsManager->isManager($node)) {
                return false;
            }
        }

        return true;
    }
}
