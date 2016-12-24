<?php

/*
 * This file is part of the Cyclear-game package.
 *
 * (c) Erik Trapman <veggatron@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cyclear\GameBundle\Form\Admin\Transfer;

use Cyclear\GameBundle\Entity\Transfer;
use Cyclear\GameBundle\Form\RennerSelectorType;
use Cyclear\GameBundle\Form\SeizoenSelectorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $seizoen = $options['seizoen'];

        switch ($options['transfertype']) {
            case Transfer::DRAFTTRANSFER:
                $builder
                    ->add('renner', RennerSelectorType::class)
                    ->add('ploegNaar', null, array('label' => 'Ploeg naar', 'required' => true, 'query_builder' => function ($e) use ($seizoen) {
                        return $e->createQueryBuilder('p')->where('p.seizoen = :seizoen')->setParameter('seizoen', $seizoen)->orderBy('p.afkorting');
                    }));
                break;
            case Transfer::ADMINTRANSFER:
                $builder
                    ->add('renner', RennerSelectorType::class, array('mapped' => false))
                    ->add('renner2', RennerSelectorType::class, array('mapped' => false, 'label' => 'Renner'));
                break;
        }

        $builder
            ->add('datum', DateType::class, array('format' => 'dd-MM-y'))
            ->add('seizoen', SeizoenSelectorType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'admin' => true,
                'seizoen' => null,
                'transfertype' => Transfer::DRAFTTRANSFER)
        );
    }


    public function getName()
    {
        return 'cyclear_gamebundle_transfertype';
    }
}
