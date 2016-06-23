<?php
    use Phalcon\Mvc\Model;
    use Phalcon\Mvc\Model\Message;
    use Phalcon\Mvc\Model\Validator\Uniqueness;
    use Phalcon\Mvc\Model\Validator\InclusionIn;
    
    class links extends Model
    {   
        public function validation()
        {
            if ($this->validationHasFailed() == true) {
                return false;
            }
            
        }
    }
?>