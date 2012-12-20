<?php

namespace Cyclear\GameBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cyclear\GameBundle\Entity\Ploeg
 *
 * @ORM\Table(name="Ploeg")
 * @ORM\Entity(repositoryClass="Cyclear\GameBundle\Entity\PloegRepository")
 */
class Ploeg
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * 
     * @ORM\ManyToOne(targetEntity="Cyclear\GameBundle\Entity\User", inversedBy="ploeg")
     */
    private $user;

    /**
     * @var string $naam
     *
     * @ORM\Column(name="naam", type="string", length=255)
     */
    private $naam;

    /**
     * @var string $afkorting
     *
     * @ORM\Column(name="afkorting", type="string", length=6)
     */
    private $afkorting;

    /**
     * 
     * @ORM\ManyToOne(targetEntity="Cyclear\GameBundle\Entity\Seizoen")
     */
    private $seizoen;

    public function __construct()
    {
    }

    /**
     * @return the $user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param field_type $user
     */
    public function setUser($user)
    {
        $this->user = $user;
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
     * Set naam
     *
     * @param string $naam
     */
    public function setNaam($naam)
    {
        $this->naam = $naam;
    }

    /**
     * Get naam
     *
     * @return string 
     */
    public function getNaam()
    {
        return $this->naam;
    }

    /**
     * @return the $afkorting
     */
    public function getAfkorting()
    {
        return $this->afkorting;
    }

    /**
     * @param string $afkorting
     */
    public function setAfkorting($afkorting)
    {
        $this->afkorting = $afkorting;
    }

    public function __toString()
    {
        return (string) $this->getNaam();
    }

    public function getSeizoen()
    {
        return $this->seizoen;
    }

    public function setSeizoen($seizoen)
    {
        $this->seizoen = $seizoen;
    }

    public function getNaamWithSeizoen()
    {
        return $this->getSeizoen()->getIdentifier().' '.$this->getNaam();
    }
}