<?php

namespace Chamilo\CourseBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * CWikiDiscuss
 *
 * @ORM\Table(name="c_wiki_discuss")
 * @ORM\Entity
 */
class CWikiDiscuss
{
    /**
     * @var integer
     *
     * @ORM\Column(name="iid", type="integer", precision=0, scale=0, nullable=false, unique=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $iid;

    /**
     * @var integer
     *
     * @ORM\Column(name="c_id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $cId;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="publication_id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $publicationId;

    /**
     * @var integer
     *
     * @ORM\Column(name="userc_id", type="integer", precision=0, scale=0, nullable=false, unique=false)
     */
    private $usercId;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="text", precision=0, scale=0, nullable=false, unique=false)
     */
    private $comment;

    /**
     * @var string
     *
     * @ORM\Column(name="p_score", type="string", length=255, precision=0, scale=0, nullable=true, unique=false)
     */
    private $pScore;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="dtime", type="datetime", precision=0, scale=0, nullable=false, unique=false)
     */
    private $dtime;


    /**
     * Get iid
     *
     * @return integer
     */
    public function getIid()
    {
        return $this->iid;
    }

    /**
     * Set cId
     *
     * @param integer $cId
     * @return CWikiDiscuss
     */
    public function setCId($cId)
    {
        $this->cId = $cId;

        return $this;
    }

    /**
     * Get cId
     *
     * @return integer
     */
    public function getCId()
    {
        return $this->cId;
    }

    /**
     * Set id
     *
     * @param integer $id
     * @return CWikiDiscuss
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set publicationId
     *
     * @param integer $publicationId
     * @return CWikiDiscuss
     */
    public function setPublicationId($publicationId)
    {
        $this->publicationId = $publicationId;

        return $this;
    }

    /**
     * Get publicationId
     *
     * @return integer
     */
    public function getPublicationId()
    {
        return $this->publicationId;
    }

    /**
     * Set usercId
     *
     * @param integer $usercId
     * @return CWikiDiscuss
     */
    public function setUsercId($usercId)
    {
        $this->usercId = $usercId;

        return $this;
    }

    /**
     * Get usercId
     *
     * @return integer
     */
    public function getUsercId()
    {
        return $this->usercId;
    }

    /**
     * Set comment
     *
     * @param string $comment
     * @return CWikiDiscuss
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Get comment
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set pScore
     *
     * @param string $pScore
     * @return CWikiDiscuss
     */
    public function setPScore($pScore)
    {
        $this->pScore = $pScore;

        return $this;
    }

    /**
     * Get pScore
     *
     * @return string
     */
    public function getPScore()
    {
        return $this->pScore;
    }

    /**
     * Set dtime
     *
     * @param \DateTime $dtime
     * @return CWikiDiscuss
     */
    public function setDtime($dtime)
    {
        $this->dtime = $dtime;

        return $this;
    }

    /**
     * Get dtime
     *
     * @return \DateTime
     */
    public function getDtime()
    {
        return $this->dtime;
    }
}
