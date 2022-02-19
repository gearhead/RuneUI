#!/usr/bin/perl

# Version 1.0 of this script was cobbled together by 
#     Frank Rysanek <rysanek (at) fccps.cz>
# Use for whatever purpose you want.
# Provided as is. I disclaim any liability.
#
# Extended by petrnik. => version 1.1
# Bugfix version 1.2, based on feedback from KeithG (commas in the xorg.conf)

# There are actually two or three different matrices. See xinput list-props.
# Of the following two, either should work for runtime calibration.
my $matrix_prop_name = "Coordinate Transformation Matrix";
#my $matrix_prop_name = "libinput Calibration Matrix";

### try to get our hands on the desired device
print ("Using \'xinput_calibrator --list\' to find some pointer devices:\n");

open(DEVLIST, "xinput_calibrator --list |");

my $dev_idx = 0;
my @devices;
my $device_desired = "";

while(<DEVLIST>)
{
	if (/Device/)
	{
		if (/"(.*)"/)
		{
			$dev_idx++;            # pre-increment. To start from 1, and to detect "no devices".
			my $devname = $1;
			$devname =~ s/\s+$//;  # remove trailing blank space
			print $dev_idx . ".) " . $devname . "\n";
			$devices[$dev_idx] = $devname;
		}
		else
		{
			die "Device name not found...";
		}
	}
}

close(DEVLIST);

($dev_idx > 0) || die "No eligible pointer devices detected in the system?";

if ($dev_idx > 1)
{
	print "Which device do you want to calibrate? Type a number and press <enter>\n";
	my $choice = <STDIN>;
	chomp($choice);

	if ($choice =~ /^[0-9]+$/)
	{
		if ($choice == 0)
		{
			die "0 is not a valid choice here :-) Min = 1. Try again.";
		}

		if ($choice > $dev_idx)
		{
			die "$choice is out of range here :-) Max = $dev_idx. Try again.";
		}

		# by now we should have a valid choice.
		$device_desired = $devices[$choice];
		print "You chose $choice = device \"$device_desired\"\n";
	}
	else
	{
		die "\"$choice\" is not a valid choice here :-) Try again.";
	}
}
else
{
	# we have single choice only
	$device_desired = $devices[1];
	print "Using device \"$device_desired\"\n";
}


### find out screen resolution 
# FIXME: there's no check that the screen is coupled to the desired device!
my $xres;
my $yres;

open(XRANDR, "xrandr |");

while (<XRANDR>)
{
	if (/current ([0-9]+) x ([0-9]+)/)
	{
		$xres = $1;
		$yres = $2;
		print "Screen resolution: $xres x $yres\n";
	}
}

close(XRANDR);


### Cancel previous calibration to start with a clean slate
print "We'd better cancel previous calibration, by inserting a unity matrix.\n";
my $matrix_update_cmd = "xinput set-prop \"$device_desired\" \"$matrix_prop_name\" 1, 0, 0, 0, 1, 0, 0, 0, 1";
system("$matrix_update_cmd");

### run the xinput_calibrator and wait for its output
my @clicks;
print "Launching the xinput_calibrator.\n";

open(CALIBRATOR, "xinput_calibrator --verbose --device \"$device_desired\" |");  # you may want to add additional args here if necessary!

while (<CALIBRATOR>)
{
	if (/^DEBUG: Adding click ([0-3]) \(X=([0-9]+), Y=([0-9]+)\)$/)
	{
		print "Click $1 : X=$2, Y=$3\n";
		# Normalize X and Y to <0,1>
		$clicks[$1] = { 'X' => $2 / $xres, 'Y' => $3 / $yres};
	}
}

close(CALIBRATOR);

### get the average left/right X and upper/lower Y
my $x0x = ($clicks[0]->{"X"} + $clicks[2]->{"X"}) / 2 ;
my $x0y = ($clicks[0]->{"Y"} + $clicks[2]->{"Y"}) / 2 ;
my $x1x = ($clicks[1]->{"X"} + $clicks[3]->{"X"}) / 2 ;
my $x1y = ($clicks[1]->{"Y"} + $clicks[3]->{"Y"}) / 2 ;
my $y0x = ($clicks[0]->{"X"} + $clicks[1]->{"X"}) / 2 ;
my $y0y = ($clicks[0]->{"Y"} + $clicks[1]->{"Y"}) / 2 ;
my $y1x = ($clicks[2]->{"X"} + $clicks[3]->{"X"}) / 2 ;
my $y1y = ($clicks[2]->{"Y"} + $clicks[3]->{"Y"}) / 2 ;

### get the difference right-left X and lower-upper Y
my $dxx = $x1x - $x0x;
my $dxy = $x1y - $x0y;
my $dyx = $y1x - $y0x;
my $dyy = $y1y - $y0y;

##########################
# Calculation equations:
# 1.	x0x * a + x0y * b + c = 1/8	(the left point equation)
# 2.	x1x * a + x1y * b + c = 7/8	(the right point equation)
# 3.	a / b = dxx / dxy		(tg alfa)
# Subtract 1. from 2.:
# 4.	(x1x - x0x) * a + (x1y - x0y) * b = 6/8
# 5.	dxx * a + dxy * b = 6/8
# Isolate (b) from 3.:
# 6.	b = a * dxy / dxx
# Substitute (b) in 5.:
# 7.	dxx * a + dxy * a * dxy / dxx = 6/8
# Isolate (a) from 7.:
# 8.	a * (dxx + dxy^2 / dxx) = 6/8
# 9.	a * (dxx^2 + dxy^2) / dxx = 6/8
# 10.	a = 6/8 * dxx / (dxx^2 + dxy^2)	!RESULT!
# Analogically for (b):
# 11.	b = 6/8 * dxy / (dxx^2 + dxy^2) !RESULT!
# We will get (c) substituting (a) and (b) in 1.
# For (d), (e) and (f) we use the same solution analogically.


### calculate the special sauce coefficients
my $a = (6 / 8) *  ($dxx / (($dxx * $dxx) + ($dxy * $dxy)));
my $b = (6 / 8) *  ($dxy / (($dxx * $dxx) + ($dxy * $dxy)));
my $c = (1 / 8) - ($a * $x0x) - ($b * $x0y);
my $d = (6 / 8) *  ($dyx / (($dyx * $dyx) + ($dyy * $dyy)));
my $e = (6 / 8) *  ($dyy / (($dyx * $dyx) + ($dyy * $dyy)));
my $f = (1 / 8) - ($d * $y0x) - ($e * $y0y);

my $matrix = "$a, $b, $c, $d, $e, $f, 0, 0, 1";
#print "Matrix = $matrix\n";


### Try to apply the settings immediately
print "Will try to apply the matrix immediately. This is still just a runtime setting.\n";

my $matrix_update_cmd = "xinput set-prop \"$device_desired\" \"$matrix_prop_name\" $matrix";
print "Matrix update cmd:\n$matrix_update_cmd\n";
system("$matrix_update_cmd");

print "By now, the input device might as well be calibrated.\n";
print "To check, try:\nxinput list-props \"$device_desired\"\n\n";
print "To make the changes permanent, create or edit a file called\n";
print "    /etc/X11/xorg.conf.d/99-calibration.conf\n";
print "or in some distro\'s (debian based) called\n";
print "    /usr/share/X11/xorg.conf.d/99-calibration.conf\n";
print "and make it look like this (feel free to copy+paste):\n\n";

# No commas in the X config file ! yikes...
$matrix =~ s/,//g;

print "Section \"InputClass\"\n";
print "	Identifier	\"calibration\"\n";
print "	MatchProduct	\"$device_desired\"\n";
print "	Option		\"TransformationMatrix\" \"$matrix\"\n";
print "EndSection\n\n";

open my $out, '>', "99-calibration.conf" or die "Can't write file $!";

print $out "#Generated by matrix.pl\n";
print $out "Section \"InputClass\"\n";
print $out "	Identifier	\"calibration\"\n";
print $out "	MatchProduct	\"$device_desired\"\n";
print $out "	Option		\"TransformationMatrix\" \"$matrix\"\n";
print $out "EndSection\n\n";
close $out;

0;
