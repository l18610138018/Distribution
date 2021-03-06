<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Repository;

use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Doctrine\ORM\EntityRepository;

class DirectoryRepository extends EntityRepository
{
    public function findRootDirectories()
    {
        return $this->_em
            ->createQuery('
                SELECT directory 
                FROM Claroline\\CoreBundle\\Entity\\Resource\\Directory directory
                JOIN directory.resourceNode node
                WHERE node.parent is NULL
            ')
            ->getResult();
    }

    public function findDefaultUploadDirectories(Workspace $workspace)
    {
        return $this->_em
            ->createQuery("
                SELECT directory
                FROM Claroline\\CoreBundle\\Entity\\Resource\\Directory directory
                JOIN directory.resourceNode node
                JOIN node.workspace workspace
                WHERE workspace.id = {$workspace->getId()}
                AND directory.uploadDestination = true
            ")
            ->getResult();
    }
}
