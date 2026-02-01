<?php

namespace bb\classes;

use bb\Db;
use bb\models\User;
use Carbon\Traits\Date;

class Task
{
  private $id;

  /**
   * @var \DateTime
   */
  private $creation_datetime;

  private $create_who;
  private $target_who;
  private $status; //new, acknowledged, done, rejected, deleted
  private $priority;
  private $task_text;

  /**
   * @var \DateTime
   */
  private $start;

  /**
   * @var \DateTime
   */
  private $deadline;

  /**
   * @var \DateTime
   */
  private $endDateTime;

  private $comments;
  private $changed;

  /**
   *
   */
  public function __construct()
  {
    $this->status='new';
  }

  /**
   * @return \DateTime
   */
  public function getEndDateTime(): \DateTime
  {
    return $this->endDateTime;
  }

  /**
   * @param \DateTime $endDateTime
   */
  public function setEndDateTime(\DateTime $endDateTime): void
  {
    $this->endDateTime = $endDateTime;
  }


  /**
   * @return mixed
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param mixed $id
   */
  public function setId($id): void
  {
    $this->id = $id;
  }

  /**
   * @return \DateTime
   */
  public function getCreationDatetime(): \DateTime
  {
    return $this->creation_datetime;
  }

  /**
   * @param \DateTime $date
   */
  public function setCreationDatetime(\DateTime $date): void
  {
    $this->creation_datetime = $date;
  }

  /**
   * @return mixed
   */
  public function getCreateWho()
  {
    return $this->create_who;
  }

  /**
   * @param mixed $create_who
   */
  public function setCreateWho($create_who): void
  {
    $this->create_who = $create_who;
  }

  /**
   * @return mixed
   */
  public function getTargetWho()
  {
    return $this->target_who;
  }

  /**
   * @param mixed $target_who
   */
  public function setTargetWho($target_who): void
  {
    $this->target_who = $target_who;
  }

  /**
   * @return string
   */
  public function getStatus(): string
  {
    return $this->status;
  }

  /**
   * @param string $status
   */
  public function setStatus(string $status): void
  {
    $this->status = $status;
  }

  /**
   * @return mixed
   */
  public function getPriority()
  {
    return $this->priority;
  }

  /**
   * @param mixed $priority
   */
  public function setPriority($priority): void
  {
    $this->priority = $priority;
  }

  /**
   * @return mixed
   */
  public function getTaskText()
  {
    return $this->task_text;
  }

  /**
   * @param mixed $task_text
   */
  public function setTaskText($task_text): void
  {
    $this->task_text = $task_text;
  }

  /**
   * @return \DateTime
   */
  public function getStart(): \DateTime
  {
    return $this->start;
  }

  /**
   * @param \DateTime $start
   */
  public function setStart(\DateTime $start): void
  {
    $this->start = $start;
  }

  /**
   * @return \DateTime
   */
  public function getDeadline(): \DateTime
  {
    return $this->deadline;
  }

  /**
   * @param \DateTime $deadline
   */
  public function setDeadline(\DateTime $deadline): void
  {
    $this->deadline = $deadline;
  }

  /**
   * @return mixed
   */
  public function getComments()
  {
    return $this->comments;
  }

  /**
   * @param mixed $comments
   */
  public function setComments($comments): void
  {
    $this->comments = $comments;
  }

  /**
   * @return mixed
   */
  public function getChanged()
  {
    return $this->changed;
  }

  /**
   * @param mixed $changed
   */
  public function setChanged($changed): void
  {
    $this->changed = $changed;
  }

  /**
   * @return bool|void
   */
  public function save(){
    if ($this->id<1) return $this->saveNew();
    else return $this->update();
  }

  public function getStatusTextName(){
    switch ($this->getStatus()){
      case 'new':
        return 'новая задача';
        break;
      case 'acknowledged':
        return 'в процессе';
        break;
      case 'done':
        return 'выполнено';
        break;
      case 'approved':
        return 'принято';
        break;
      default:
        return $this->getStatus();
        break;
    }
  }

  /**
   * @return bool|void
   */
  public function saveNew(){
    $mysqli = Db::getInstance()->getConnection();
    $query = "INSERT INTO tasks SET creation_datetime='".$this->creation_datetime->format('Y-m-d H:i')."', create_who='$this->create_who',	target_who='$this->target_who',
              `status`='$this->status', priority='$this->priority', task_text='$this->task_text', start='".($this->start ? $this->start->format('Y-m-d H:i') : 'NULL')."',
              end_date_time='".($this->endDateTime ? $this->endDateTime->format('Y-m-d H:i') : 'NULL')."', deadline='".$this->deadline->format('Y-m-d H:i')."',
              comments='".addslashes($this->comments)."'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    $this->setId($mysqli->insert_id);

    return true;
  }

  /**
   * @return bool|void
   */
  public function delete(){
    $mysqli = Db::getInstance()->getConnection();

    $query = "DELETE FROM tasks WHERE id = '$this->id'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    return true;
  }

  /**
   * @return bool|void
   */
  public function update(){
    $mysqli = Db::getInstance()->getConnection();
    $query = "UPDATE tasks SET creation_datetime='".$this->creation_datetime->format('Y-m-d H:i')."', 	create_who='$this->create_who',	target_who='$this->target_who',
              `status`='$this->status', priority='$this->priority', task_text='$this->task_text', start='".($this->start ? $this->start->format('Y-m-d H:i') : 'NULL')."',
              end_date_time='".($this->endDateTime ? $this->endDateTime->format('Y-m-d H:i') : 'NULL')."', deadline='".$this->deadline->format('Y-m-d H:i')."',
              comments='".addslashes($this->comments)."', changed='".((new \DateTime())->format('Y-m-d H:i'))."'
              WHERE id='$this->id'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    return true;
  }

  /**
   * @param $row
   * @return Task
   * @throws \Exception
   */
  public static function createFromDbArray($row){
    $task = new self();

    $task->setId($row['id']);
    $task->setCreationDatetime(new \DateTime($row['creation_datetime']));
    $task->setCreateWho($row['create_who']);
    $task->setTargetWho($row['target_who']);
    $task->setStatus($row['status']);
    $task->setPriority($row['priority']);
    $task->setTaskText($row['task_text']);
    $task->setStart(new \DateTime($row['start']));
    $task->setDeadline(new \DateTime($row['deadline']));
    $task->setEndDateTime(new \DateTime($row['end_date_time']));
    $task->setComments($row['comments']);
    $task->setChanged(new \DateTime($row['changed']));

    return $task;
  }

  /**
   * @param $targetId
   * @param $status
   * @return Task[]|false|void
   * @throws \Exception
   */
  public static function getAllForUserStatus($targetId='all', $status='new'){
    if ($targetId=='0') return [];
    $restrictions = [];
      if ($targetId!='all') $restrictions[]="target_who='$targetId'";
      if ($status!='all') {
        if ($status=='actual') $restrictions[]="`status` IN ('new', 'acknowledged')";
        else $restrictions[]="`status`='$status'";
      }

      $srch="";
      if (count($restrictions)>0) $srch=Db::makeQueryConditionFromArray($restrictions);


    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM tasks $srch";
    //echo $query;
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return false;

    $approovedShowLimit = new \DateTime();
      $approovedShowLimit->modify('-2 weeks');

    $rez = [];
    while ($row=$result->fetch_assoc()) {
      $tt=self::createFromDbArray($row);
      if ($tt->isApproved() && $tt->getEndDateTime()<$approovedShowLimit) continue;
      $rez[]=$tt;
    }

    return $rez;
  }

  /**
   * @param $id
   * @return Task|false|void
   * @throws \Exception
   */
  public static function getById($id){
    $mysqli = Db::getInstance()->getConnection();
    $query = "SELECT * FROM tasks WHERE id='$id'";
    $result = $mysqli->query($query);
    if (!$result) die('Сбой при доступе к базе данных: '.$query.' ('.$mysqli->connect_errno.') '. $mysqli->connect_error);

    if ($result->num_rows<1) return false;

    $row=$result->fetch_assoc();
    $rez=self::createFromDbArray($row);

    return $rez;
  }

  /**
   * @param $who_id
   * @param $target_id
   * @param $task_text
   * @param \DateTime $deadline
   * @return Task
   */
  public static function createTask($who_id, $target_id, $task_text, \DateTime $deadline){
    $t=new self();
    $today = new \DateTime();
      $t->setCreateWho($who_id);
      $t->setTargetWho($target_id);
      $t->setCreationDatetime($today);
      $t->setTaskText($task_text);
      $t->setDeadline($deadline);
      $t->setStatus('new');
    $t->save();
    return $t;
  }

  /**
   * @param $message
   * @return void
   */
  public function addMessage($message){
    $this->comments .= self::createMessageHtml($message);
  }

  /**
   * @param $message
   * @return mixed
   */
  private function createMessageHtml($message){
    $user = User::getCurrentUser();
    $datetime = new \DateTime();

    $oldRez='
       <div class="comment">
         <div class="datetime">'.$datetime->format('d.m.y').'<sup>'.$datetime->format('H:i').'</sup></div>
         <div class="who">'.$user->getShortName().':</div>
         <div class="message">'.htmlspecialchars($message).'</div>
       </div>
    ';

    $rez='<div class="comment">
         <div class="datetime"><span>'.$datetime->format('d/m/y').'</span><span>'.$datetime->format('H:i').'</span></div>
         <div class="message">'.htmlspecialchars($message).'</div>
       </div>
    ';

    return $rez;
  }

  /**
   * @return bool
   */
  public function isNew(){
    if ($this->getStatus()=='new') return true;
    else return false;
  }

  /**
   * @return bool
   */
  public function isNewOrInProcess(){
    if ($this->getStatus()=='new' || $this->getStatus()=='acknowledged') return true;
    else return false;
  }

  /**
   * @return bool
   */
  public function isDone(){
    if ($this->getStatus()=='done') return true;
    else return false;
  }

  /**
   * @return bool
   */
  public function isApproved(){
    if ($this->getStatus()=='approved') return true;
    else return false;
  }

  /**
   * @return void
   */
  public function makeAccepted(){
    $this->setStatus('acknowledged');
    //$this->addMessage('прочитано');
  }

  /**
   * @return void
   */
  public function makeDone(){
    $today = new \DateTime();
    $this->setStatus('done');
    $this->setEndDateTime($today);
    //$this->addMessage('выполнено');
  }
  /**
   * @return void
   */
  public function makeApproved(){
    $today = new \DateTime();
    $this->setStatus('approved');
    //$this->addMessage('выполнено');
  }

  /**
   * @return void
   */
  public function makeRemoved(){
    $today = new \DateTime();
    $this->setStatus('deleted');
    $this->setEndDateTime($today);
    $this->addMessage('удалено');
  }

  /**
   * @return bool
   */
  public function isPastDue(){
    if ($this->isDone()) {
      $today = $this->getEndDateTime();
      if ($today>$this->getDeadline()) return true;
      else return false;
    }
    else {
      $today = new \DateTime();
      if ($today>$this->getDeadline()) return true;
      else return false;
    }
  }

  /**
   * @return bool
   */
  public function isRemoved(){
    if ($this->getStatus()=='deleted') return true;
    else return false;
  }

  /**
   * @param $taskText
   * @param \DateTime $deadline
   * @param $comment
   * @return bool
   */
  public function makeOwnerChanges($taskText, \DateTime $deadline, $comment){
    $message = 'изменено: ';
    $tmpBool = false;
    if ($this->getTaskText() != $taskText) {
      $message.='текст задачи;';
      $this->setTaskText($taskText);
      $tmpBool=true;
    }
    if ($this->deadline != $deadline) {
      $message .= ' срок: '.$this->getDeadline()->format('d.m.y (H:i)').'-->'.$deadline->format('d.m.y (H:i)').';';
      $this->setDeadline($deadline);
      $tmpBool=true;
    }
    if ($tmpBool) {
      if (trim($comment)!=''){
        $message.=' комментарий: '.$comment;
      }

      $this->addMessage($message);
    }
    else{
      if($message!='изменено:<br>'){
        $this->addMessage($comment);
      }
    }


    return true;
  }

  /**
   * @return bool
   */
  public function isEditableForOwner(){
    $user = User::getCurrentUser();
    if ($user->isManagement() && $user->getId() == $this->getCreateWho() && !($this->isRemoved() || $this->isDone())) {
      return true;
    }
    else{
      return false;
    }
  }

}
