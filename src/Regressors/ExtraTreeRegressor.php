<?php

namespace Rubix\ML\Regressors;

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Graph\Nodes\Comparison;
use InvalidArgumentException;

/**
 * Extra Tree Regressor
 *
 * An Extremely Randomized Regression Tree. Extra Trees differ from standard
 * Regression Trees in that they choose a random split drawn from max features.
 * When max features is set to 1 this amounts to building a totally random
 * tree. Extra Tree can be used in an Ensemble, such as Bootstrap Aggregator, or
 * by itself, however, it is generally considered a weak learner by itself.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class ExtraTreeRegressor extends RegressionTree
{
    /**
     * Randomized algorithm to that choses the split point with the lowest gini
     * impurity among a random selection of $maxFeatures features.
     *
     * @param  \Rubix\ML\Datasets\Labeled  $dataset
     * @return \Rubix\ML\Graph\Nodes\Comparison
     */
    protected function findBestSplit(Labeled $dataset) : Comparison
    {
        $best = [
            'ssd' => INF, 'index' => null, 'value' => null, 'groups' => [],
        ];

        shuffle($this->indices);

        foreach (array_slice($this->indices, 0, $this->maxFeatures) as $index) {
            $sample = $dataset->row(rand(0, count($dataset) - 1));

            $value = $sample[$index];

            $groups = $dataset->partition($index, $value);

            $ssd = $this->calculateSsd($groups);

            if ($ssd < $best['ssd']) {
                $best['ssd'] = $ssd;
                $best['index'] = $index;
                $best['value'] = $value;
                $best['groups'] = $groups;
            }

            if ($ssd <= $this->tolerance) {
                break 1;
            }
        }

        return new Comparison($best['index'], $best['value'], $best['groups'],
            $best['ssd']);
    }
}