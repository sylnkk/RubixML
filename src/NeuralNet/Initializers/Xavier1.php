<?php

namespace Rubix\ML\NeuralNet\Initializers;

use Rubix\Tensor\Matrix;

/**
 * Xavier 1
 *
 * The Xavier 1 initializer draws from a uniform distribution [-limit, limit]
 * where *limit* is squal to sqrt(6 / (fanIn + fanOut)). This initializer is
 * best suited for layers that feed into an activation layer that outputs a
 * value between 0 and 1 such as Softmax or Sigmoid.
 *
 * References:
 * [1] X. Glorot et al. (2010). Understanding the Difficulty of Training Deep
 * Feedforward Neural Networks.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class Xavier1 implements Initializer
{
    /**
     * Initialize a weight matrix W in the dimensions fan in x fan out.
     *
     * @param  int  $fanIn
     * @param  int  $fanOut
     * @return \Rubix\Tensor\Matrix
     */
    public function init(int $fanIn, int $fanOut) : Matrix
    {
        $scale = sqrt(6. / ($fanIn + $fanOut));

        return Matrix::uniform($fanOut, $fanIn)
            ->multiply($scale);
    }
}
