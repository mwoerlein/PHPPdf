<?php
namespace PHPPdf\Core\Node;

use PHPPdf\Core\Point;
use PHPPdf\Core\Document;
use PHPPdf\Core\DrawingTask;
use PHPPdf\Core\DrawingTaskHeap;
use PHPPdf\Exception\InvalidArgumentException;
use VectorGraphics\Model\Graphic;

class VectorGraphic extends Container
{
    protected static function setDefaultAttributes()
    {
        parent::setDefaultAttributes();
        
        static::addAttribute('function');
        static::addAttribute('param');
    }
    
    protected static function initializeType()
    {
        parent::initializeType();
    
        static::setAttributeSetters(array('function' => 'setFunction'));
    }
    
    public function setFunction($function)
    {
        if (is_callable($callable = explode("::", $function))) {
            $this->setAttributeDirectly('function', $callable);
        } elseif (file_exists($samplePath = $this->getSamplePath($function))) {
            $this->setAttributeDirectly('function', function () use ($samplePath) { return require $samplePath; });
        } else {
            throw new InvalidArgumentException("function not found: '$function'");
        }
    }
    
    protected function doDraw(Document $document, DrawingTaskHeap $tasks)
    {
        parent::doDraw($document, $tasks);
        
        $callback = function(VectorGraphic $node, Document $document, Point $lowerLeft, Graphic $graphic) {
            $node->getGraphicsContext()->drawVectorGraphic(
                $graphic,
                $lowerLeft->getX(),
                $lowerLeft->getY(),
                $node->getRealWidth(),
                $node->getRealHeight()
            );
        };
        
        $translation = $this->getPositionTranslation();
        $lowerLeft = $this->getRealFirstPoint()->translate($translation->getX(), $translation->getY() + $this->getRealHeight());
        $graphic = call_user_func($this->getAttribute('function'), $this->getAttribute('param'));
        if (!$graphic instanceof Graphic) {
            throw new InvalidArgumentException('Generator function has to return a graphic!');
        }
        $tasks->insert(new DrawingTask($callback, [$this, $document, $lowerLeft, $graphic], /* between background and border */45));
    }
    
    /**
     * @param $sample
     *
     * @return string
     */
    private function getSamplePath($sample)
    {
        return __DIR__ . "/../../../../vendor/mwoerlein/vector-graphics/examples/$sample.php";
    }
}
