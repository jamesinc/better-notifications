<?php if (!defined('APPLICATION')) exit();

$PluginInfo['BetterNotifications'] = array(
	'Description' => 'Implements vBulletin/PHPBB-style e-mail notifications for users who bookmark discussions. They will only receive one notification e-mail until they re-visit the discussion.',
	'Version' => '2.0.1',
	'Author' => 'James Ducker',
	'RequiredApplications' => array('Vanilla' => '2.1'),
	'AuthorEmail' => 'james.ducker@gmail.com',
	'AuthorUrl' => 'http://github.com/jamesinc'
);

class BetterNotifications extends Gdn_Plugin {

	public function Setup() {

		$this->Structure();

	}

	public function Structure() {

		// Add a new column, "Notified", to the UserDiscussion table
		// This tracks whether or not a user should receive an e-mail
		// about updates to a bookmarked discussion.

		Gdn::Structure()
		->Table('UserDiscussion')
		->Column('Notified', 'tinyint(1)', 0)
		->Set();

	}

	public function DiscussionController_Render_Before( $Sender ) {

		$Session = Gdn::Session();
		$UserID = $Session->UserID;
		$DiscussionID = $Sender->DiscussionModel->EventArguments['Discussion']->DiscussionID;
		
		$this->SetNotified( $UserID, $DiscussionID, FALSE );

	}

	public function ActivityModel_BeforeSendNotification_Handler( $Sender ) {

		$Activity = $Sender->EventArguments['Activity'];
		$Email = $Sender->EventArguments['Email'];

		// $Activity will be an array or an object, depending on where the handler has been called from,
		// so we access using the val() function to get around the ambiguity.
		$ActivityType = val('ActivityType', $Activity);

		$BookmarkComment = $ActivityType == 'Comment' || $ActivityType == 'BookmarkComment';

		if ( $BookmarkComment ) {

			$UserID = val('NotifyUserID', $Activity);

			// This is the dodgiest part of the plugin
			// In order to get the discussion ID, I have to get the commentID associated with
			// the activity. I can use that ID to lookup the corresponding discussion.
			// The only way (that I can see) to get the commentID for the activity is to
			// scrape it from the activity route.
			preg_match( "/comment\/([0-9]+)/", val('Route', $Activity), $RouteMatches );

			$CommentID = $RouteMatches[1];

			// Get disucsionID
			$DiscussionID = Gdn::SQL()
				->Select('DiscussionID')
				->From('Comment')
				->Where('CommentID', $CommentID)
				->Get()
				->FirstRow()
				->DiscussionID;
				
			if ( $this->GetNotified($UserID, $DiscussionID) ) {

				// Stop the user from receiving a notification
				// by wiping the e-mail data
				$Email->Clear();
			
			} else {

				$this->SetNotified( $UserID, $DiscussionID, TRUE );

			}

		}

	}

	public function GetNotified( $UserID, $DiscussionID ) {

		$Notified = Gdn::SQL()
			->Select('Notified')
			->From('UserDiscussion')
			->Where('UserID', $UserID)
			->Where('DiscussionID', $DiscussionID)
			->Get()
			->FirstRow()
			->Notified;

		return $Notified == '1';

	}

	public function SetNotified( $UserID, $DiscussionID, $Notified = FALSE ) {

		$FlagValue = $Notified == FALSE ? '0' : '1';

		Gdn::SQL()
			->Update('UserDiscussion')
			->Set('Notified', $Notified)
			->Where('DiscussionID', $DiscussionID)
			->Where('UserID', $UserID)
			->Put();

	}

}

?>