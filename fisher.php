<?php
$play = null; //we can use singleton but we don't do it now :)

$log_file = 'fisher.log';
function trace($msg)
{
	global $play, $log_file;
	$str = "";
	if ($play)
		$str .= '['.$play->state_string().'] ';
	$str .= $msg."\n";
	echo $str;
	file_put_contents($log_file, $str, FILE_APPEND);
	flush();
}


class Play
{
	const RIVERSIDE_LEFT = 0;
	const RIVERSIDE_RIGHT = 1;

	public $riversides = array(); // 2 arrays for every riverside
	public $boat = null;

	function __construct($left_side, $right_side)
	{
		$this->riversides[self::RIVERSIDE_LEFT] = $left_side;
		$this->riversides[self::RIVERSIDE_RIGHT] = $right_side;
		$this->boat = new Boat();
	}

	function turn($fishers_count, $parents_count, $children_count)
	{
		try
		{
			$passengers_for_boat = array('Fisher' => $fishers_count, 'Ancestor' => $parents_count, 'Child' => $children_count);
			//load on boat
			foreach ($this->riversides[$this->boat->side] as $idx => $riverside_passenger) {
				foreach ($passengers_for_boat as $class => &$count)
					if ($count > 0 && is_a($riverside_passenger, $class)) {
						$count--;
						$this->boat->load_passenger($riverside_passenger);
						unset($this->riversides[$this->boat->side][$idx]);
					}
			}

			//check if have enough passangers on riverside
			if ($passengers_for_boat['Child'] > 0)
			{
				throw new Exception('Turn failed: not enough children on the side.');
			}
			if ($passengers_for_boat['Ancestor'] > 0)
			{
				throw new Exception('Turn failed: not enough parents on the side.');
			}
			if ($passengers_for_boat['Fisher'] > 0)
			{
				throw new Exception('Turn failed: not enough fishers on the side.');
			}

			//move boat
			$this->boat->move();

			//unload boat
			for ($i=0; $i<($fishers_count + $parents_count + $children_count); $i++) {
				array_push($this->riversides[$this->boat->side], $this->boat->unload_passenger());
			}
		}
		catch (Exception $e) {
			trace($e->getMessage());
			exit;
		}
	}

	function is_finished()
	{
		return count($this->riversides[self::RIVERSIDE_LEFT]) == 1 && reset($this->riversides[self::RIVERSIDE_LEFT]) instanceof Fisher;
	}

	function current_side()
	{
		return $this->boat->side;
	}
	function front_side()
	{
		return $this->boat->side == self::RIVERSIDE_LEFT ? self::RIVERSIDE_RIGHT : self::RIVERSIDE_LEFT;
	}
	function state_string()
	{
		$sides = array(
			self::RIVERSIDE_LEFT => array('Fisher' => 0, 'Ancestor' => 0, 'Child' => 0),
			self::RIVERSIDE_RIGHT => array('Fisher' => 0, 'Ancestor' => 0, 'Child' => 0)
		);
		foreach ($this->riversides as $side_id => $riverside)
			foreach ($riverside as $passenger)
				foreach ($sides[$side_id] as $class => &$count)
					if (is_a($passenger, $class))
						$count++;
		return sprintf('%df%dp%dc%s%df%dp%dc', $sides[self::RIVERSIDE_LEFT]['Fisher'], $sides[self::RIVERSIDE_LEFT]['Ancestor'], $sides[self::RIVERSIDE_LEFT]['Child'],
			$this->boat->side == self::RIVERSIDE_LEFT ? '< ' : ' >',
			$sides[self::RIVERSIDE_RIGHT]['Fisher'], $sides[self::RIVERSIDE_RIGHT]['Ancestor'], $sides[self::RIVERSIDE_RIGHT]['Child']);
	}
}

class Passenger
{
	public $size = null;
	function __construct($size)
	{
		$this->size = $size;
	}
}

class Adult extends Passenger
{
	function __construct()
	{
		parent::__construct(1);
	}
}
class Child extends Passenger
{
	function __construct()
	{
		parent::__construct(0.5);
	}
}
class Ancestor extends Adult //Parent is reserved :)
{
}

class Fisher extends Adult
{
}

class Father extends Ancestor
{
}
class Mother extends Ancestor
{
}
class Son extends Child
{
}
class Daughter extends Child
{
}

class Boat
{
	const SIZE = 1;

	public $empty_space = self::SIZE;
	private $passengers = array();

	public $side = Play::RIVERSIDE_LEFT;

	public function load_passenger($passenger)
	{
		if ($this->empty_space >= $passenger->size)
		{
			$this->empty_space -= $passenger->size;
			array_push($this->passengers, $passenger);
			trace('Load on boat: '. get_class($passenger));
		}
		else
		{
			throw new Exception('Load is failed. The boat is full');
		}
	}

	public function unload_passenger()
	{
		$passenger = array_pop($this->passengers);
		if ($passenger != null)
		{
			$this->empty_space += $passenger->size;
			trace('Unload from boat: '. get_class($passenger));
			return $passenger;
		}
		else
		{
			throw new Exception('There is noting to unload. The boat is empty');
		}
	}

	public function move()
	{
		$this->side = $this->side == Play::RIVERSIDE_LEFT ? Play::RIVERSIDE_RIGHT : Play::RIVERSIDE_LEFT;
		trace('Boat move: left '. ($this->side == Play::RIVERSIDE_RIGHT ? '-->' : '<--') . ' right');
	}
}



$play = new Play(
	array(
		new Fisher(),

		new Father(),
		new Mother(),

		new Son(),
		new Daughter()
	),
	array()
);
trace('//////////////////////////////////');
trace('/* solving with canonical rules */');
trace('//////////////////////////////////');

//start: 1f 2p 2c (1 fisher, 2 parents, 2 children) on left side <--> 0 passengers on right side
$play->turn(0, 0, 2);//f2p   2c-->  2c    \
$play->turn(0, 0, 1);//f2p1c 1c<--  1c    | 1. In this step we moved first parent to another side.
$play->turn(0, 1, 0);//f1p1c 1p-->  1p1c  | The Boat is in the same state.
$play->turn(0, 0, 1);//f1p2c 1c<--  1p    /
$play->turn(0, 0, 2);//f1p   2c-->  1p2c  \
$play->turn(0, 0, 1);//f1p1c 1c<--  1p1c  | 2. In this step we moved second parent to another side.
$play->turn(0, 1, 0);//f1c   1p-->  2p1c  | The Boat is in the same state.
$play->turn(0, 0, 1);//f2c   1c<--  2p    /
$play->turn(0, 0, 2);//f     2c-->  2p2c  \
$play->turn(0, 0, 1);//f1c   1c<--  2p1c  | 3. In this step we moved fisher to another side.
$play->turn(1, 0, 0);//1c    f -->  f2p1c | The Boat is in the same state.
$play->turn(0, 0, 1);//2c    1c<--  f2p   /
$play->turn(0, 0, 2);//      2c-->  f2p2c - We have 2 children and we just move them to another side.
$play->turn(1, 0, 0);//f     f <--  2p2c  - We should move fisher back. As you see the task now is as simple as possible. Congratulation! The achievement is unlocked! :)

if ($play->is_finished()) {
	trace('We finished!');
}
else {
	trace('Something is wrong.');
}


//////////////////////////////////
/* More powerful stratagy       */
//////////////////////////////////

class PowerPlay extends Play
{
	function run_stratagy()
	{
		try {
			$parents_count = count(array_filter($this->riversides[self::RIVERSIDE_LEFT], function ($passenger) {
				return is_a($passenger, 'Ancestor');
			}));
			$children_count = count(array_filter($this->riversides[self::RIVERSIDE_LEFT], function ($passenger) {
				return is_a($passenger, 'Child');
			}));

			//move parents
			for ($i=0; $i<$parents_count; $i++)
				$this->move_parent();

			//move all childs except last 2
			for ($i=2; $i<$children_count; $i++)
				$this->move_child();

			//final accord
			$this->move_fisher();
			$this->move_2child_and_fisher_back();
		}
		catch (Exception $e) {
			trace($e->getMessage());
			exit;
		}
	}

	function enough($curside_fishers, $curside_parents, $curside_childs, $frontside_fishers, $frontside_parents, $frontside_childs)
	{
		$map = array(array('Fisher', $this->current_side()), array('Ancestor', $this->current_side()), array('Child', $this->current_side()),
			array('Fisher', $this->front_side()), array('Ancestor', $this->front_side()), array('Child', $this->front_side()));

		$input_vector = array($curside_fishers, $curside_parents, $curside_childs, $frontside_fishers, $frontside_parents, $frontside_childs);
		$counts_vector = array(0, 0, 0, 0, 0, 0);

		foreach ($this->riversides as $side_id => $passengers)
			foreach ($passengers as $passenger)
				foreach ($map as $idx => $m)
					if (is_a($passenger, $m[0]) && $side_id==$m[1])
						$counts_vector[$idx]++;

		foreach ($input_vector as $key => $count)
			if ($count > $counts_vector[$key])
				return false;
		return true;
	}

	function move_parent()
	{
		if ($this->enough(0,1,2,0,0,0))
		{
			$this->turn(0, 0, 2);//f2p   2c-->  2c    \
			$this->turn(0, 0, 1);//f2p1c 1c<--  1c    | 1. In this step we moved first parent to another side.
			$this->turn(0, 1, 0);//f1p1c 1p-->  1p1c  | The Boat is in the same state.
			$this->turn(0, 0, 1);//f1p2c 1c<--  1p    /
			trace('Strategy: 4 turns step: parent moved');
		}
		else
		{
			throw new Exception('Step failed: Not enough passengers on riversides');
		}
	}
	function move_fisher()
	{
		if ($this->enough(1,0,2,0,0,0))
		{
			$this->turn(0, 0, 2);//f     2c-->  2p2c  \
			$this->turn(0, 0, 1);//f1c   1c<--  2p1c  | 3. In this step we moved fisher to another side.
			$this->turn(1, 0, 0);//1c    f -->  f2p1c | The Boat is in the same state.
			$this->turn(0, 0, 1);//2c    1c<--  f2p   /
			trace('Strategy: 4 turns step: fisher moved');
		}
		else
		{
			throw new Exception('Step failed: Not enough passengers on riversides');
		}
	}

	function move_child()
	{
		if ($this->enough(0,0,2,0,0,0))
		{
			$this->turn(0, 0, 2);//      2c-->  f2p2c - We have 2 children and we just move them to another side.
			$this->turn(0, 0, 1);//f     f <--  2p2c  - We should move fisher back. As you see the task now is as simple as possible. Congratulation! The achievement is unlocked! :)
			trace('Strategy: 2 turns step: 1 child moved');
		}
		else
		{
			throw new Exception('Step failed: Not enough children on current riverside');
		}
	}

	//iteration with side effect
	function move_2child_and_fisher_back()
	{
		if ($this->enough(0,0,2,1,0,0))
		{
			$this->turn(0, 0, 2);//      2c-->  f2p2c - We have 2 children and we just move them to another side.
			$this->turn(1, 0, 0);//f     f <--  2p2c  - We should move fisher back. As you see the task now is as simple as possible. Congratulation! The achievement is unlocked! :)
			trace('Strategy: 2 turns step: 2 children moved forward and fisher get back');
		}
		else
		{
			throw new Exception('Step failed: Not enough passengers on riversides');
		}
	}
}

trace('///////////////////////////////////////');
trace('/* Strategy example with 2 daughters */');
trace('///////////////////////////////////////');
$play = new PowerPlay(
	array(
		new Fisher(),

		new Father(),
		new Mother(),

		new Son(),
		new Daughter(),
		new Daughter()
	),
	array()
);

$play->run_stratagy();

if ($play->is_finished()) {
	trace('We finished!');
}
else {
	trace('Something is wrong.');
}
