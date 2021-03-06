<?php

namespace Rubix\ML\Clusterers;

use Rubix\ML\Estimator;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\DataFrame;
use Rubix\ML\Kernels\Distance\Distance;
use Rubix\ML\Kernels\Distance\Euclidean;
use InvalidArgumentException;

/**
 * DBSCAN
 *
 * Density-Based Spatial Clustering of Applications with Noise is a clustering
 * algorithm able to find non-linearly separable and arbitrarily-shaped clusters.
 * In addition, DBSCAN also has the ability to mark outliers as *noise* and thus
 * can be used as a quasi Anomaly Detector.
 *
 * > **Note**: Noise samples are assigned the cluster number *-1*.
 *
 * References:
 * [1] M. Ester et al. (1996). A Densty-Based Algorithm for Discovering Clusters.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class DBSCAN implements Estimator
{
    const NOISE = -1;

    /**
     * The maximum distance between two points to be considered neighbors. The
     * smaller the value, the tighter the clusters will be.
     *
     * @var float
     */
    protected $radius;

    /**
     * The minimum number of points to from a dense region or cluster.
     *
     * @var int
     */
    protected $minDensity;

    /**
     * The distance kernel to use when computing the distances between points.
     *
     * @var \Rubix\ML\Kernels\Distance\Distance
     */
    protected $kernel;

    /**
     * @param  float  $radius
     * @param  int  $minDensity
     * @param  \Rubix\ML\Kernels\Distance\Distance|null  $kernel
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(float $radius = 0.5, int $minDensity = 5, ?Distance $kernel = null)
    {
        if ($radius <= 0.) {
            throw new InvalidArgumentException('Cluster radius must be'
                . " greater than 0, $radius given.");
        }

        if ($minDensity < 0) {
            throw new InvalidArgumentException('Minimum density must be'
                . " greater than 0, $minDensity given.");
        }

        if (is_null($kernel)) {
            $kernel = new Euclidean();
        }

        $this->radius = $radius;
        $this->minDensity = $minDensity;
        $this->kernel = $kernel;
    }

    /**
     * Return the integer encoded estimator type.
     *
     * @return int
     */
    public function type() : int
    {
        return self::CLUSTERER;
    }

    /**
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        if ($dataset->typeCount(DataFrame::CONTINUOUS) !== $dataset->numColumns()) {
            throw new InvalidArgumentException('This estimator only works'
                . ' with continuous features.');
        }

        $predictions = [];
        $cluster = 0;

        $samples = $dataset->samples();

        foreach ($samples as $i => $sample) {
            if (isset($predictions[$i])) {
                continue 1;
            }

            $neighbors = $this->groupNeighbors($sample, $samples);

            if (count($neighbors) < $this->minDensity) {
                $predictions[$i] = self::NOISE;

                continue 1;
            }

            $predictions[$i] = $cluster;

            while ($neighbors) {
                $index = array_pop($neighbors);

                if (isset($predictions[$index])) {
                    if ($predictions[$index] === self::NOISE) {
                        $predictions[$index] = $cluster;
                    }

                    continue 1;
                }

                $predictions[$index] = $cluster;

                $seeds = $this->groupNeighbors($samples[$index], $samples);

                if (count($seeds) >= $this->minDensity) {
                    $neighbors = array_unique(array_merge($neighbors, $seeds), SORT_REGULAR);
                }
            }

            $cluster++;
        }

        return $predictions;
    }

    /**
     * Group the samples that are within a given radius of the center into a
     * neighborhood and return the indices.
     *
     * @param  array  $center
     * @param  array  $samples
     * @return array
     */
    protected function groupNeighbors(array $center, array $samples) : array
    {
        $neighborhood = [];

        foreach ($samples as $index => $sample) {
            $distance = $this->kernel->compute($center, $sample);

            if ($distance <= $this->radius) {
                $neighborhood[] = $index;
            }
        }

        return $neighborhood;
    }
}
