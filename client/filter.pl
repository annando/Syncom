#!/usr/bin/env perl -w
#
use LWP::UserAgent;

# -- Configuration:
#

my $ACCESS_URI   = 'http://10.10.14.50/syncom/api/closed/'; # edit to match the URI used for closed requests
my $DO_LOG       = 1; # set to 0 to disable logging
# ---------------------------------------------------------------------------

my $UA  = LWP::UserAgent->new;

$UA->agent('SynCom::perl_filter/0.1');
push @{ $UA->requests_redirectable }, 'POST';

sub filter_art() {
	if ($DO_LOG == 1) {
		open(LOG, ">/tmp/filter.log") or die "Unable to open /tmp/filter.log:$!\n";
	}
	else {
		open(LOG, ">/dev/null") or die "Unable to open /dev/null:$!\n";
	}

	if (!defined($hdr{'References'})) {
		print LOG "No References: - posting OK\n";
		close(LOG);
		return '';
	}

	print LOG "Querying.. $ACCESS_URI\n";

	my $req = HTTP::Request->new(POST => "$ACCESS_URI");

	$req->content_type('application/x-www-form-urlencoded');
	$req->content('messageid='.$hdr{'References'});

	my $res = $UA->request($req);

	print LOG 'Inquire  : '.$hdr{'References'}."\n";
	print LOG 'Response : '.$res->status_line."\n";
	print LOG 'Body     : '."\n".$res->content."\n";

	if ($res->is_success) {
		foreach (split("\n", $res->content)) {
			chomp;

			if (/1/) {
				print LOG "Thread is closed.\n";
				close(LOG);

				return 'Posting to closed threads is not allowed.'; # closed
			}
			if (/0/) {
				print LOG "Posting OK.\n";
				close(LOG);

				return '';
			}
		}
	}
	print LOG "Posting OK (no block/unblock in response)\n";
	close(LOG);

	return '';
}

