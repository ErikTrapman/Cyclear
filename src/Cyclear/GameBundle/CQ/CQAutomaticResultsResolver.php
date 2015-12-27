<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\CQ;

use Cyclear\GameBundle\CQ\Exception\CyclearGameBundleCQException;
use Cyclear\GameBundle\CQ\Exception\NotEnoughContentException;
use Cyclear\GameBundle\Entity\Seizoen;
use Cyclear\GameBundle\Entity\Uitslag;
use Cyclear\GameBundle\Entity\Wedstrijd;
use Cyclear\GameBundle\EntityManager\RennerManager;
use Cyclear\GameBundle\EntityManager\UitslagManager;
use Cyclear\GameBundle\Form\DataTransformer\RennerNameToRennerIdTransformer;
use Doctrine\ORM\EntityManager;
use ErikTrapman\Bundle\CQRankingParserBundle\DataContainer\RecentRaceDataContainer;
use ErikTrapman\Bundle\CQRankingParserBundle\Parser\Crawler\CrawlerManager;
use ErikTrapman\Bundle\CQRankingParserBundle\Parser\Exception\CQParserException;
use ErikTrapman\Bundle\CQRankingParserBundle\Parser\RecentRaces\RecentRacesParser;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

class CQAutomaticResultsResolver
{

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var RaceCategoryMatcher
     */
    private $categoryMatcher;

    /**
     * @var UitslagManager
     */
    private $uitslagManager;

    /**
     * @var CrawlerManager
     */
    private $crawlerManager;

    /**
     * @var RennerManager
     */
    private $rennerManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RennerNameToRennerIdTransformer
     */
    private $transformer;


    public function __construct(
        EntityManager $em,
        RaceCategoryMatcher $raceCategoryMatcher,
        UitslagManager $uitslagManager,
        CrawlerManager $crawlerManager,
        RennerNameToRennerIdTransformer $transformer,
        LoggerInterface $logger)
    {
        $this->em = $em;
        $this->categoryMatcher = $raceCategoryMatcher;
        $this->uitslagManager = $uitslagManager;
        $this->crawlerManager = $crawlerManager;
        $this->rennerManager = new RennerManager();
        $this->logger = $logger;
        $this->transformer = $transformer;
    }


    /**
     * @param array $races
     * @param Seizoen $seizoen
     * @param \DateTime $start
     * @param \DateTime $end
     * @param int $max
     * @return Wedstrijd[]
     * @throws CyclearGameBundleCQException
     */
    public function resolve(array $races, Seizoen $seizoen, \DateTime $start, \DateTime $end, $max = 1)
    {
        $start = clone $start;
        $start->setTime(0, 0, 0);
        $end = clone $end;
        $end->setTime(0, 0, 0);
        $repo = $this->em->getRepository('CyclearGameBundle:Wedstrijd');
        $ploegRepo = $this->em->getRepository('CyclearGameBundle:Ploeg');
        $ret = [];
        foreach ($races as $i => $race) {

            // we simply use the url as external identifier
            $wedstrijd = $repo->findOneByExternalIdentifier($race->url);
            if ($wedstrijd) {
                continue;
            }
            if ($race->date < $start || $race->date > $end) {
                continue;
            }
            $wedstrijd = new Wedstrijd();
            $date = clone $race->date;
            $date->setTime(0, 0, 0);
            $wedstrijd->setDatum($date);
            $wedstrijd->setExternalIdentifier($race->url);
            $wedstrijd->setNaam($race->name);
            $wedstrijd->setSeizoen($seizoen);

            $type = $this->categoryMatcher->getUitslagTypeAccordingToCategory($race->category);
            if (null === $type) {
                $this->logger->notice('Unable to resolve uitslagtype from category `' . $race->category . '``');
                continue;
            }
            $wedstrijd->setGeneralClassification($type->isGeneralClassification());
            $wedstrijdRefDate = null;
            if ($this->categoryMatcher->needsRefStage($wedstrijd)) {
                $this->categoryMatcher->getRefStage($wedstrijd);
            }
            // TODO parametrize CQ-urls!
            $uitslagen = $this->uitslagManager->prepareUitslagen(
                $type,
                $this->crawlerManager->getCrawler('http://cqranking.com/men/asp/gen/' . $race->url),
                $wedstrijd,
                $seizoen);
            if (count($uitslagen) < $type->getMaxResults()) {
                $this->logger->notice($race->url . ' has not enough content yet');
                continue;
            }
            foreach ($uitslagen as $uitslagRow) {
                $uitslag = new Uitslag();
                $uitslag->setWedstrijd($wedstrijd);
                $uitslag->setPloeg($uitslagRow['ploeg'] ? $ploegRepo->find($uitslagRow['ploeg']) : null);
                $uitslag->setRennerPunten($uitslagRow['rennerPunten']);
                $uitslag->setPloegPunten($uitslagRow['ploegPunten']);
                $uitslag->setPositie($uitslagRow['positie']);
                $uitslag->setRenner($this->transformer->reverseTransform($uitslagRow['renner']));
                $wedstrijd->addUitslag($uitslag);
            }
            $ret[] = $wedstrijd;
            if (count($ret) == $max) {
                break;
            }
        }
        return $ret;
    }

}