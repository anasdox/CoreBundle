<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Manager;

use Claroline\CoreBundle\Library\Testing\MockeryTestCase;
use Claroline\CoreBundle\Entity\User;

class MessageManagerTest extends MockeryTestCase
{
    private $om;
    private $pagerFactory;
    private $groupRepo;
    private $userRepo;
    private $messageRepo;
    private $userMessageRepo;
    private $manager;

    public function setUp()
    {
        parent::setUp();
        $this->om = $this->mock('Claroline\CoreBundle\Persistence\ObjectManager');
        $this->pagerFactory = $this->mock('Claroline\CoreBundle\Pager\PagerFactory');
        $this->groupRepo = $this->mock('Claroline\CoreBundle\Repository\GroupRepository');
        $this->userRepo = $this->mock('Claroline\CoreBundle\Repository\UserRepository');
        $this->messageRepo = $this->mock('Claroline\CoreBundle\Repository\MessageRepository');
        $this->userMessageRepo = $this->mock('Claroline\CoreBundle\Repository\UserMessageRepository');
        $this->om->shouldReceive('getRepository')
            ->with('ClarolineCoreBundle:Group')
            ->andReturn($this->groupRepo);
        $this->om->shouldReceive('getRepository')
            ->with('ClarolineCoreBundle:User')
            ->andReturn($this->userRepo);
        $this->om->shouldReceive('getRepository')
            ->with('ClarolineCoreBundle:Message')
            ->andReturn($this->messageRepo);
        $this->om->shouldReceive('getRepository')
            ->with('ClarolineCoreBundle:UserMessage')
            ->andReturn($this->userMessageRepo);
        $this->manager = new MessageManager($this->om, $this->pagerFactory);
    }

    public function testSend()
    {
        $sender = new User();
        $receiverA = new User();
        $receiverB = new User();
        $msg = $this->mock('Claroline\CoreBundle\Entity\Message');
        $msgParent = $this->mock('Claroline\CoreBundle\Entity\Message');
        $userMessageA = $this->mock('Claroline\CoreBundle\Entity\UserMessage');
        $userMessageB = $this->mock('Claroline\CoreBundle\Entity\UserMessage');
        $userMessageC = $this->mock('Claroline\CoreBundle\Entity\UserMessage');

        $msg->shouldReceive('getTo')->once()->andReturn('user1;user2');
        $this->userRepo->shouldReceive('findByUsernames')
            ->once()
            ->with(array('user1','user2'))
            ->andReturn(array($receiverA, $receiverB));
        $msg->shouldReceive('setSender')->once()->with($sender);
        $msg->shouldReceive('setParent')->once()->with($msgParent);
        $this->om->shouldReceive('persist')->once()->with($msg);
        $this->om->shouldReceive('factory')
            ->times(3)
            ->with('Claroline\CoreBundle\Entity\UserMessage')
            ->andReturn($userMessageA, $userMessageB, $userMessageC);
        $userMessageA->shouldReceive('setIsSent')->once()->with(true);
        $userMessageA->shouldReceive('setUser')->once()->with($sender);
        $userMessageA->shouldReceive('setMessage')->once()->with($msg);
        $userMessageB->shouldReceive('setUser')->once()->with($receiverA);
        $userMessageB->shouldReceive('setMessage')->once()->with($msg);
        $userMessageC->shouldReceive('setUser')->once()->with($receiverB);
        $userMessageC->shouldReceive('setMessage')->once()->with($msg);
        $this->om->shouldReceive('persist')->once()->with($userMessageA);
        $this->om->shouldReceive('persist')->once()->with($userMessageB);
        $this->om->shouldReceive('persist')->once()->with($userMessageC);
        $this->om->shouldReceive('flush')->once();

        $this->manager->send($sender, $msg, $msgParent);
    }

    /**
     * @dataProvider getMessagesProvider
     */
    public function testGetMessages($repoMethod, $managerMethod)
    {
        $user = $this->mock('Claroline\CoreBundle\Entity\User');
        $query = new \Doctrine\ORM\Query($this->mock('Doctrine\ORM\EntityManager'));
        $this->userMessageRepo->shouldReceive($repoMethod)->once()->with($user, '')->andReturn($query);
        $this->pagerFactory->shouldReceive('createPager')->once()->with($query, 1)->andReturn('pager');
        $this->assertEquals('pager', $this->manager->{$managerMethod}($user, '', 1));
    }

    /**
     * @dataProvider findMessagesProvider
     */
    public function testFindMessages($repoMethod, $search, $managerMethod)
    {
        $user = $this->mock('Claroline\CoreBundle\Entity\User');
        $query = new \Doctrine\ORM\Query($this->mock('Doctrine\ORM\EntityManager'));
        $this->userMessageRepo->shouldReceive($repoMethod)->once()->with($user, $search, false)->andReturn($query);
        $this->pagerFactory->shouldReceive('createPager')->once()->with($query, 1)->andReturn('pager');
        $this->assertEquals('pager', $this->manager->{$managerMethod}($user, $search, 1));
    }

    public function getMessagesProvider()
    {
        return array(
            array('findReceived', 'getReceivedMessages'),
            array('findSent', 'getSentMessages'),
            array('findRemoved', 'getRemovedMessages')
        );
    }

    public function findMessagesProvider()
    {
        return array(
            array('findReceivedByObjectOrSender', 'foo', 'getReceivedMessages'),
            array('findSentByObject', 'foo', 'getSentMessages'),
            array('findRemovedByObjectOrSender', 'foo', 'getRemovedMessages')
        );
    }

    /**
     * @dataProvider testMarkAsRemovedProvider
     */
    public function testSetMarkAsRemoved($flag, $managerMethod)
    {
        $user = $this->mock('Claroline\CoreBundle\Entity\User');
        $usrMsgA = $this->mock('Claroline\CoreBundle\Entity\UserMessage');
        $usrMsgB = $this->mock('Claroline\CoreBundle\Entity\UserMessage');
        $this->userMessageRepo->shouldReceive('findByMessages')
            ->once()
            ->with($user, array('message1', 'message2'))
            ->andReturn(array($usrMsgA, $usrMsgB));
        $usrMsgA->shouldReceive('markAs' . $flag)->once();
        $usrMsgB->shouldReceive('markAs' . $flag)->once();
        $this->om->shouldReceive('persist')->with($usrMsgA)->once();
        $this->om->shouldReceive('persist')->with($usrMsgB)->once();
        $this->om->shouldReceive('flush')->once();
        $this->manager->{$managerMethod}(array($usrMsgA, $usrMsgB));
    }

    public function testMarkAsRemovedProvider()
    {
        return array(
            array('Removed','markAsRemoved'),
            array('Unremoved','markAsUnremoved')
        );
    }

    public function testGetConversation()
    {
        $msg = $this->mock('Claroline\CoreBundle\Entity\Message');

        $this->messageRepo->shouldReceive('findAncestors')->with($msg)->andReturn($msg);
        $this->assertEquals($msg, $this->manager->getConversation($msg));
    }

    public function testRemove()
    {
        $usrMsg = $this->mock('Claroline\CoreBundle\Entity\UserMessage');

        $this->om->shouldReceive('remove')->with($usrMsg)->once();
        $this->om->shouldReceive('flush')->once();
        $this->manager->remove(array($usrMsg));
    }

    public function testgenerateGroupeQrStr()
    {
        $group = $this->mock('Claroline\CoreBundle\Entity\Group');
        $users = array();

        for ($i = 0; $i < 3; $i++) {
            $user = $this->mock('Claroline\CoreBundle\Entity\User');
            $user->shouldReceive('getId')->once()->andReturn($i);
            $users[] = $user;
            }

        $this->userRepo->shouldReceive('findByGroup')->once()->with($group)->andReturn($users);
        $this->assertEquals('?ids[]=0&ids[]=1&ids[]=2', $this->manager->generateGroupQueryString($group));
     }
}
