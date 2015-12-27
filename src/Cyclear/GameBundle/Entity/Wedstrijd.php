<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cyclear\GameBundle\Entity\Wedstrijd
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Wedstrijd
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
     * @var datetime $datum
     *
     * @ORM\Column(name="datum", type="datetime")
     */
    private $datum;

    /**
     * @var string $naam
     *
     * @ORM\Column(name="naam", type="string", length=255)
     */
    private $naam;

    /**
     *
     * @ORM\OneToMany(targetEntity="Cyclear\GameBundle\Entity\Uitslag", mappedBy="wedstrijd", cascade={"all"})
     * @ORM\OrderBy({"positie" = "ASC"})
     */
    private $uitslagen;

    /**
     *
     * @ORM\ManyToOne(targetEntity="Cyclear\GameBundle\Entity\Seizoen")
     */
    private $seizoen;

    /**
     * @ORM\Column(type="boolean")
     */
    private $generalClassification = false;

    /**
     * @ORM\Column(nullable=true)
     */
    private $externalIdentifier;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $fullyProcessed;

    public function __construct()
    {
        $this->uitslagen = new ArrayCollection();
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
     * Set datum
     *
     * @param datetime $datum
     */
    public function setDatum($datum)
    {
        $this->datum = $datum;
    }

    /**
     * Get datum
     *
     * @return datetime
     */
    public function getDatum()
    {
        return $this->datum;
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
     * @return Uitslag[]|ArrayCollection
     */
    public function getUitslagen()
    {
        return $this->uitslagen;
    }


    public function __toString()
    {
        return $this->getNaam();
    }


    public function getSeizoen()
    {
        return $this->seizoen;
    }

    public function setSeizoen($seizoen)
    {
        $this->seizoen = $seizoen;
    }

    /**
     * @return mixed
     */
    public function isGeneralClassification()
    {
        return $this->generalClassification;
    }

    /**
     * @param mixed $generalClassification
     */
    public function setGeneralClassification($generalClassification)
    {
        $this->generalClassification = $generalClassification;
    }

    /**
     * @return mixed
     */
    public function getExternalIdentifier()
    {
        return $this->externalIdentifier;
    }

    /**
     * @param mixed $externalIdentifier
     */
    public function setExternalIdentifier($externalIdentifier)
    {
        $this->externalIdentifier = $externalIdentifier;
    }

    /**
     * @return mixed
     */
    public function getFullyProcessed()
    {
        return $this->fullyProcessed;
    }

    /**
     * @param mixed $fullyProcessed
     */
    public function setFullyProcessed($fullyProcessed)
    {
        $this->fullyProcessed = $fullyProcessed;
    }

    public function addUitslag(Uitslag $uitslag)
    {
        if (!$this->uitslagen->contains($uitslag)) {
            $this->uitslagen->add($uitslag);
        }
    }

}