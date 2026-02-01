<?php

namespace App\Http\Controllers;

use App\MyClasses\CatMainPage;
use App\MyClasses\L2ModelWeb;
use bb\classes\Model;
use bb\classes\ModelWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use PhpParser\Node\Expr\AssignOp\Mod;

class SearchController extends Controller
{
    //
    public function search($lang, Request $req) {
      $time1 = new \DateTime();
        $text = trim($req->input('search'));
        $p = new CatMainPage();

        $p->setPageTitle('Результаты поиска детских товаров напрокат в Минске:');
        $p->setH1Title('Результаты поиска по запросу: "'.$text.'"');
        $p->addBreadCrumbItem('поиск', '');

        $modelIdArray = ModelWeb::getModelIdsFullTextSearch($text);
      $time2 = new \DateTime();
        if (is_array($modelIdArray) && count($modelIdArray)>0) {
            foreach ($modelIdArray as $mid) {
                if ($l2m = L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
            }
        }
      $time3 = new \DateTime();
        //$p->setBlock1('start: '.$time1->format("H:i::s").'<br>'.'start2: '.$time2->format("H:i::s").'<br>'.'start3: '.$time3->format("H:i::s").'<br>');

        return view('search', ['p' => $p]);
    }

    public function ageFilter($lang, Request $req) {
        $from = intval($req->input('age_from'));
        $to = intval($req->input('age_to'));

        $p = new CatMainPage();

        $p->setPageTitle('Детские товары напрокат в Минске: от '.$from.' мес., до '.$to.' мес.');

        switch ($from) {
            case 0;
                $p->setH1Title('от 0 до 6 месяцев');
            break;
            case 6:
                $p->setH1Title('от 6 месяцев до года');
                break;
            case 12:
                $p->setH1Title('от года до 1.5 лет');
                break;
            case 18:
                $p->setH1Title('от 1.5 до 2 лет');
                break;
            case 24:
                $p->setH1Title('от 2 до 3 лет');
                break;
            case 36:
                $p->setH1Title('3+ лет');
                break;
            default:
                $p->setH1Title('от '.$from.' до '.$to.' месяцев');
                break;
        }
        $p->addBreadCrumbItem('по возрасту', '');
        $p->setShowAgeFilter(1);

        $modelIdArray = Model::getModelIdsArrayByAge($from, $to);

        if (is_array($modelIdArray) && count($modelIdArray)>0) {
            foreach ($modelIdArray as $mid) {
                if ($l2m = L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
            }
        }

        return view('search', ['p' => $p]);
    }

    public function producerFilter($lang, Request $req){
        $producer = $req->input('producer');
        //echo 'ddd'.$producer;
        $p = new CatMainPage();

        $p->setPageTitle('Детские товары '.$producer);
        $p->addBreadCrumbItem('по возрасту', '');

        $modelIdArray = Model::getModelIdsArrayByProducer($producer);

        if (is_array($modelIdArray) && count($modelIdArray)>0) {
            foreach ($modelIdArray as $mid) {
                if ($l2m = L2ModelWeb::getL2ModelWebById($mid)) $p->addL2ModelWeb($l2m);
            }
        }

        return view('search', ['p' => $p]);
    }
}
