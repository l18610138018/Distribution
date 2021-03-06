<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\VideoPlayerBundle\Twig;

use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\VideoPlayerBundle\Manager\VideoPlayerManager;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service
 * @DI\Tag("twig.extension")
 */
class VideoExtension extends \Twig_Extension
{
    private $container;

    /**
     * @DI\InjectParams({
     *     "manager" = @DI\Inject("claroline.manager.video_player_manager")
     * })
     */
    public function __construct(VideoPlayerManager $manager)
    {
        $this->manager = $manager;
    }

    public function getFunctions()
    {
        return [
            'get_video_tracks' => new \Twig_SimpleFunction('get_video_tracks', [$this, 'getVideoTracks']),
        ];
    }

    public function getName()
    {
        return 'video_extension';
    }

    public function getVideoTracks(File $video)
    {
        return $this->manager->getTracksByVideo($video);
    }
}
