<?php

namespace Rubix\ML\NeuralNet\Layers;

use Rubix\Tensor\Matrix;
use Rubix\Tensor\Vector;
use Rubix\ML\NeuralNet\Parameter;
use Rubix\ML\Other\Helpers\Stats;
use Rubix\ML\NeuralNet\Optimizers\Optimizer;
use InvalidArgumentException;
use RuntimeException;

/**
 * Batch Norm
 *
 * Normalize the activations of the previous layer such that the mean activation
 * is close to 0 and the activation standard deviation is close to 1. Batch Norm
 * can be used to reduce the amount of covariate shift within the network
 * making it possible to use higher learning rates and converge faster under
 * some circumstances.
 *
 * References:
 * [1] S. Ioffe et al. (2015). Batch Normalization: Accelerating Deep Network
 * Training by Reducing Internal Covariate Shift.
 * [2] T. F. Chan et al. (1979). Updating Formulae and a Pairwise Algorithm for
 * Computing Sample Variances.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class BatchNorm implements Hidden, Parametric
{
    /**
     * The width of the layer. i.e. the number of neurons.
     *
     * @var int|null
     */
    protected $width;

    /**
     * The learnable centering parameter.
     *
     * @var \Rubix\ML\NeuralNet\Parameter|null
     */
    protected $beta;

    /**
     * The learnable scaling parameter.
     *
     * @var \Rubix\ML\NeuralNet\Parameter|null
     */
    protected $gamma;

    /**
     * The running mean of each input dimension.
     *
     * @var \Rubix\Tensor\Vector|null
     */
    protected $mean;

    /**
     * The running variance of each input dimension.
     *
     * @var \Rubix\Tensor\Vector|null
     */
    protected $variance;

    /**
     * The running standard deviation of each input dimension.
     *
     * @var \Rubix\Tensor\Vector|null
     */
    protected $stddev;

    /**
     * The number of training samples that have passed through the layer so far.
     *
     * @var int|null
     */
    protected $n;

    /**
     * A cache of inverse standard deviations calculated during the forward pass.
     *
     * @var \Rubix\Tensor\Matrix|null
     */
    protected $stdInv;

    /**
     * A cache of normalized inputs to the layer.
     *
     * @var \Rubix\Tensor\Matrix|null
     */
    protected $xHat;

    /**
     * Return the width of the layer.
     * 
     * @return int|null
     */
    public function width() : ?int
    {
        return $this->width;
    }

    /**
     * Initialize the layer with the fan in from the previous layer and return
     * the fan out for this layer.
     *
     * @param  int  $fanIn
     * @return int
     */
    public function init(int $fanIn) : int
    {
        $fanOut = $fanIn;

        $this->mean = Vector::zeros($fanIn);
        $this->variance = Vector::ones($fanIn);
        $this->n = 0;

        $this->width = $fanOut;

        $this->beta = new Parameter(Matrix::zeros($fanIn, 1));
        $this->gamma = new Parameter(Matrix::ones($fanIn, 1));

        return $fanOut;
    }

    /**
     * Compute a forward pass through the layer.
     *
     * @param  \Rubix\Tensor\Matrix  $input
     * @throws \RuntimeException
     * @return \Rubix\Tensor\Matrix
     */
    public function forward(Matrix $input) : Matrix
    {
        if (is_null($this->n) or empty($this->mean) or empty($this->variance) or is_null($this->beta) or is_null($this->gamma)) {
                throw new RuntimeException('Layer has not been initialized.');
        }

        $beta = $this->beta->w()->column(0);
        $gamma = $this->gamma->w()->column(0);

        $n = $input->n();

        $oldMeans = $this->mean->asArray();
        $oldVariances = $this->variance->asArray();
        $oldWeight = $this->n;

       $newMeans = $newVars = $stddevs = $stdInv = $xHat = $out = [];

        foreach ($input as $i => $row) {
            list($mean, $variance) = Stats::meanVar($row);

            $oldMean = $oldMeans[$i];

            $newMeans[] = (($n * $mean)
                + ($oldWeight * $oldMean))
                / ($oldWeight + $n);

            $newVars[] = ($oldWeight
                * $oldVariances[$i] + ($n * $variance)
                + ($oldWeight / ($n * ($oldWeight + $n)))
                * ($n * $oldMean - $n * $mean) ** 2)
                / ($oldWeight + $n);

            $stddev = sqrt($variance ?: self::EPSILON);

            $gHat = $gamma[$i];
            $bHat = $beta[$i];

            $stdInvRow = $xHatRow = $outRow = [];

            foreach ($row as $value) {
                $alpha = 1. / $stddev;
                $beta = $alpha * ($value - $mean);

                $stdInvRow[] = $alpha;
                $xHatRow[] = $beta;
                $outRow[] = $gHat * $beta + $bHat;
            }

            $stdInv[] = $stdInvRow;
            $xHat[] = $xHatRow;
            $out[] = $outRow;

            $stddevs[] = $stddev;
        }

        $this->mean = Vector::quick($newMeans);
        $this->variance = Vector::quick($newVars);
        $this->stddev = Vector::quick($stddevs);

        $this->stdInv = Matrix::quick($stdInv);
        $this->xHat = Matrix::quick($xHat);

        $this->n += $n;

        return Matrix::quick($out);
    }

    /**
     * Compute an inferential pass through the layer.
     *
     * @param  \Rubix\Tensor\Matrix  $input
     * @throws \RuntimeException
     * @return \Rubix\Tensor\Matrix
     */
    public function infer(Matrix $input) : Matrix
    {
        if (is_null($this->mean) or is_null($this->stddev) or is_null($this->beta) or is_null($this->gamma)) {
                throw new RuntimeException('Layer has not been initilaized.');
        }
        
        $beta = $this->beta->w()->column(0);
        $gamma = $this->gamma->w()->column(0);

        $out = [];

        foreach ($input as $i => $row) {
            $mean = $this->mean[$i];
            $stddev = $this->stddev[$i];

            $vector = [];

            foreach ($row as $value) {
                $vector[] = $gamma[$i]
                    * ($value - $mean)
                    / $stddev
                    + $beta[$i];
            }

            $out[] = $vector;
        }

        return Matrix::quick($out);
    }

    /**
     * Calculate the errors and gradients of the layer and update the parameters.
     *
     * @param  callable  $prevGradient
     * @param  \Rubix\ML\NeuralNet\Optimizers\Optimizer  $optimizer
     * @throws \RuntimeException
     * @return callable
     */
    public function back(callable $prevGradient, Optimizer $optimizer) : callable
    {
        if (is_null($this->n) or is_null($this->mean) or is_null($this->stddev) or is_null($this->beta) or is_null($this->gamma)) {
                throw new RuntimeException('Layer has not been initilaized.');
        }

        if (is_null($this->stdInv) or is_null($this->xHat)) {
            throw new RuntimeException('Must perform forward pass before'
                . ' backpropagating.');
        }

        $dOut = $prevGradient();

        $dBeta = $dOut->sum()->asColumnMatrix();
        $dGamma = $dOut->multiply($this->xHat)->sum()->asColumnMatrix();

        $this->beta->update($optimizer->step($this->beta, $dBeta));
        $this->gamma->update($optimizer->step($this->gamma, $dGamma));

        $stdInv = $this->stdInv;
        $xHat = $this->xHat;

        $gamma = $this->gamma->w()->columnAsVector(0);

        unset($this->stdInv, $this->xHat);

        return function () use ($dOut, $gamma, $stdInv, $xHat) {
            list($m, $n) = $dOut->shape();

            $dXHat = $dOut->multiply($gamma);

            $xHatSigma = $dXHat->multiply($xHat)->transpose()->sum();

            $dXHatSigma = $dXHat->transpose()->sum();

            return $dXHat->multiply($m)
                ->subtract($dXHatSigma)
                ->subtract($xHat->multiply($xHatSigma))
                ->multiply($stdInv->divide($m));
        };
    }

    /**
     * Return the parameters of the layer in an associative array.
     *
     * @throws \RuntimeException
     * @return array
     */
    public function read() : array
    {
        if (is_null($this->beta) or is_null($this->gamma)) {
                throw new RuntimeException('Layer has not been initilaized.');
        }

        return [
            'beta' => clone $this->beta,
            'gamma' => clone $this->gamma,
        ];
    }

    /**
     * Restore the parameters in the layer from an associative array.
     *
     * @param  array  $parameters
     * @return void
     */
    public function restore(array $parameters) : void
    {
        $this->beta = $parameters['beta'];
        $this->gamma = $parameters['gamma'];
    }
}
