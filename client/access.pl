#!/usr/bin/env perl -w
#
use LWP::UserAgent;
use Digest::MD5;

use Data::Dumper;

# -- Configuration:
#
my $ACCESS_URI   = 'http://10.10.14.50/syncom/api/access/'; # edit to match the URI used for auth requests
my $DEFAULT_READ = '!*,pirates.de*'; # edit to match the default read: mask
my $DEFAULT_POST = '!*,pirates.de*'; # edit to match the default post: mask
# ---------------------------------------------------------------------------
my $UA  = LWP::UserAgent->new;
my $CTX = Digest::MD5->new;

$UA->agent('SynCom::perl_access/0.1');
push @{ $UA->requests_redirectable }, 'POST';

sub access() {
	if (defined($attributes{username})) {
                open(LOG, ">/tmp/access.".$attributes{username}.".log") or die "Unable to open /tmp/access.".$attributes{username}.".log:$!\n";
        }
        else {
                open(LOG, ">/tmp/access.log") or die "Unable to open /tmp/access.log:$!\n";
        }
        #open(LOG, ">/tmp/access.log") or die "Unable to open /tmp/access.log:$!\n";
        print LOG "Querying.. $ACCESS_URI\n";

        my $req = HTTP::Request->new(POST => "$ACCESS_URI");
        my %access_params = (
                "nnrpdauthsender" => "true",
                "virtualhost" => "true",
                "newsmaster" => 'news@news01.piratenpartei.de',
                "domain" => "news01.piratenpartei.de",
                "read"     => $DEFAULT_READ,
                "post"     => $DEFAULT_POST
        );

        $CTX->reset;
        $CTX->add($attributes{intipaddr});

        $req->content_type('application/x-www-form-urlencoded');
        $req->content('username='.$attributes{username}.'&authhash='.$CTX->digest);

        my $res = $UA->request($req);

        print LOG 'Response: '.$res->status_line."\n";
        print LOG 'Body    : '."\n".$res->content."\n";

        if ($res->is_success) {
                my $read_perm = $access_params{"read"};
                my $post_perm = $access_params{"post"};

                foreach (split("\n", $res->content)) {
                        if (/^read:\s*"([^"]+)"/) {
                                $read_perm .= ','.$1;
                        }

                        if (/^post:\s*"([^"]+)"/) {
                                $post_perm .= ','.$1;
                        }
                }

                $access_params{"read"} = $read_perm;
                $access_params{"post"} = $post_perm;
        }
        else {
                close(LOG);
                die "Access query returned '".$res->status_line."'!\n";
        }

        print LOG Dumper(\%access_params);
        close(LOG);

        return %access_params;
}
