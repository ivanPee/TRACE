import React, { useMemo, useState } from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import AppDialog from '../../components/AppDialog';
import DriverProfileCard from '../../components/DriverProfileCard';
import DropdownField from '../../components/DropdownField';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

function bookingKey(booking) {
  return booking.monthlyPlanRouteId
    ? `plan-${booking.monthlyPlanRouteId}-${booking.scheduledDate}-${booking.requestedPickupTime || booking.scheduledTime}-${booking.status}`
    : `booking-${booking.id}`;
}

function sameScheduledTime(left, right) {
  return String(left.requestedPickupTime || left.scheduledTime || '').slice(0, 5) === String(right.requestedPickupTime || right.scheduledTime || '').slice(0, 5);
}

function sameRoute(left, right) {
  return left.routeDirection === right.routeDirection;
}

function routeDirectionLabel(direction) {
  return direction === 'return_home' ? 'Return home' : 'To school';
}

function serviceDateLabel(value) {
  if (!value) {
    return 'Date not set';
  }

  const [year, month, day] = String(value).split('-').map(Number);
  const date = new Date(year, (month || 1) - 1, day || 1);

  if (!Number.isFinite(date.getTime())) {
    return String(value);
  }

  return date.toLocaleDateString('en-PH', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function shortDateLabel(value) {
  if (!value) {
    return '';
  }

  const [year, month, day] = String(value).split('-').map(Number);
  const date = new Date(year, (month || 1) - 1, day || 1);

  if (!Number.isFinite(date.getTime())) {
    return String(value);
  }

  return date.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
  });
}

function monthlyPlanRangeLabel(booking) {
  if (!booking.monthlyPlanStartDate || !booking.monthlyPlanEndDate) {
    return null;
  }

  return `${shortDateLabel(booking.monthlyPlanStartDate)} to ${shortDateLabel(booking.monthlyPlanEndDate)}`;
}

function canCancelDailyTrip(booking, isParent) {
  return isParent
    && booking.isMonthlyPlan
    && ['pending', 'assigned'].includes(String(booking.status || '').toLowerCase());
}

function distanceKm(from, to) {
  const fromLat = Number(from?.latitude);
  const fromLng = Number(from?.longitude);
  const toLat = Number(to?.latitude);
  const toLng = Number(to?.longitude);

  if (![fromLat, fromLng, toLat, toLng].every(Number.isFinite)) {
    return null;
  }

  const earthRadiusKm = 6371;
  const latDelta = ((toLat - fromLat) * Math.PI) / 180;
  const lngDelta = ((toLng - fromLng) * Math.PI) / 180;
  const a = Math.sin(latDelta / 2) ** 2
    + Math.cos((fromLat * Math.PI) / 180) * Math.cos((toLat * Math.PI) / 180) * Math.sin(lngDelta / 2) ** 2;

  return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function imageUrlForMessage(message) {
  const explicitUrl = message.imageUrl ?? message.image_url ?? null;
  const text = String(message.text ?? message.message_text ?? '');
  const candidate = explicitUrl || text;

  return /\.(jpe?g|png|webp|gif)(\?.*)?$/i.test(candidate) ? candidate : null;
}

export default function BookingsScreen({ navigation }) {
  const { currentRole, currentUser, availableDrivers, bookings, rides, messages, refreshDashboard, approveBooking, rejectBooking, transferRide, cancelMonthlyPlanDay } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [transferDriverId, setTransferDriverId] = useState('');
  const [transferHandoffNote, setTransferHandoffNote] = useState('');
  const [expandedBookingKey, setExpandedBookingKey] = useState(null);
  const [dialog, setDialog] = useState(null);
  const ride = rides[0];
  const recentMessages = messages.slice(-2);
  const isDriver = currentRole === 'driver';
  const isParent = currentRole === 'parent';
  const vehicleCapacity = Number(currentUser?.vehicleCapacity || 1);
  const groupedBookings = useMemo(() => {
    const groups = new Map();

    bookings.forEach((booking) => {
      const key = bookingKey(booking);

      if (!groups.has(key)) {
        groups.set(key, {
          ...booking,
          childNames: [],
          routeChildCount: 0,
          totalPayoutAmount: 0,
          isMonthlyPlan: Boolean(booking.monthlyPlanRouteId),
        });
      }

      const group = groups.get(key);
      group.routeChildCount += 1;
      group.totalPayoutAmount += Number(booking.driverPayoutAmount || 0);

      if (booking.studentName && !group.childNames.includes(booking.studentName)) {
        group.childNames.push(booking.studentName);
      }
    });

    return Array.from(groups.values()).sort((left, right) => {
      const leftOpen = ['pending', 'assigned'].includes(String(left.status || '').toLowerCase()) ? 0 : 1;
      const rightOpen = ['pending', 'assigned'].includes(String(right.status || '').toLowerCase()) ? 0 : 1;

      if (leftOpen !== rightOpen) {
        return leftOpen - rightOpen;
      }

      const leftDate = String(left.scheduledDate || '');
      const rightDate = String(right.scheduledDate || '');

      if (leftDate !== rightDate) {
        return leftOpen === 0 ? leftDate.localeCompare(rightDate) : rightDate.localeCompare(leftDate);
      }

      return String(left.pickupTime || left.scheduledTime || '').localeCompare(String(right.pickupTime || right.scheduledTime || ''));
    });
  }, [bookings]);
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };
  const transferOptions = availableDrivers
    .filter((driver) => String(driver.id) !== String(currentUser?.driverId))
    .map((driver) => {
      const driverDistanceKm = distanceKm(
        { latitude: driver.latitude, longitude: driver.longitude },
        ride?.location || ride?.pickupLocation || null
      );
      const status = driverDistanceKm !== null ? `${driverDistanceKm.toFixed(1)} km away` : driver.isOnline ? 'Online' : 'Approved';

      return {
        label: `${driver.name} - ${driver.vehicle} (${status})`,
        value: driver.id,
        distanceKm: driverDistanceKm,
        isOnline: driver.isOnline,
      };
    })
    .sort((left, right) => {
      if (left.distanceKm !== null && right.distanceKm !== null) {
        return left.distanceKm - right.distanceKm;
      }

      if (left.distanceKm !== null) {
        return -1;
      }

      if (right.distanceKm !== null) {
        return 1;
      }

      return Number(right.isOnline) - Number(left.isOnline);
    });
  const handleTransfer = async (booking) => {
    if (!booking.rideId || !transferDriverId) {
      setDialog({
        title: 'Select a driver',
        message: 'Choose another approved driver before transferring this route.',
      });
      return;
    }

    try {
      const handoffNote = transferHandoffNote.trim() || 'Driver emergency or vehicle difficulty.';
      await transferRide(booking.rideId, transferDriverId, { handoffNote, includeRoute: true });
      await refreshDashboard();
      setTransferDriverId('');
      setTransferHandoffNote('');
      setDialog({
        title: 'Route transferred',
        message: 'The nearby driver can now see this route in their bookings. Parents were alerted.',
      });
    } catch (error) {
      setDialog({
        title: 'Cannot transfer route',
        message: error.message || 'The emergency handoff could not be completed.',
      });
    }
  };
  const claimRoute = async (booking, options = {}) => {
    try {
      await approveBooking(booking.id, options);
      await refreshDashboard();
      setDialog({
        title: 'Transport claimed',
        message: options.includeCarpool ? 'The matching route was added to your current carpool.' : 'The route is now assigned to you.',
      });
    } catch (error) {
      setDialog({
        title: 'Cannot claim route',
        message: error.message || 'The transport board could not claim this route.',
      });
    }
  };
  const skipRoute = async (booking) => {
    try {
      await rejectBooking(booking.id);
      setDialog({
        title: 'Route skipped',
        message: 'The route remains available for other drivers.',
      });
    } catch (error) {
      setDialog({
        title: 'Cannot skip route',
        message: error.message || 'The transport board could not skip this route.',
      });
    }
  };
  const cancelDailyTrip = (booking) => {
    const tripDate = booking.scheduledDate || 'this service day';

    setDialog({
      title: 'Cancel daily trip?',
      message: `Use this for no class, holiday, or student absence on ${tripDate}. The system will record a 50% refund for the cancelled daily trip and notify the assigned driver.`,
      actions: [
        { label: 'Keep Trip', variant: 'ghost' },
        {
          label: 'Cancel Trip',
          icon: 'ban',
          variant: 'primary',
          onPress: async () => {
            try {
              const response = await cancelMonthlyPlanDay(booking.id, { reason: 'No class, holiday, or student absence.' });
              await refreshDashboard();
              setDialog({
                title: 'Daily trip cancelled',
                message: response?.message || 'The cancelled service day was recorded with a 50% refund.',
                actions: [
                  { label: 'Close', variant: 'ghost' },
                  { label: 'View Transactions', icon: 'history', variant: 'primary', onPress: () => navigation.navigate('Transactions') },
                ],
              });
            } catch (error) {
              setDialog({
                title: 'Cannot cancel trip',
                message: error.message || 'Only pending or assigned monthly trips can be cancelled for refund.',
              });
            }
          },
        },
      ],
    });
  };
  const relatedRoutesFor = (booking) => groupedBookings.filter((candidate) =>
    bookingKey(candidate) !== bookingKey(booking)
    && sameScheduledTime(candidate, booking)
    && sameRoute(candidate, booking)
    && (candidate.canApprove || candidate.rideId)
  );
  const handleClaim = (booking) => {
    const carpoolMatches = relatedRoutesFor(booking);
    const routeChildren = booking.childNames?.length ? booking.childNames : [booking.studentName].filter(Boolean);
    const routeChildCount = booking.routeChildCount || routeChildren.length || 1;

    if (!carpoolMatches.length && routeChildCount <= 1) {
      claimRoute(booking);
      return;
    }

    const children = [...routeChildren, ...carpoolMatches.flatMap((candidate) => candidate.childNames?.length ? candidate.childNames : [candidate.studentName])].filter(Boolean);
    const hasCurrentRoute = carpoolMatches.some((candidate) => candidate.rideId);
    const fullRouteSeats = children.length || routeChildCount;
    const capacity = booking.vehicleCapacity || vehicleCapacity;
    const routeLabel = `${String(booking.requestedPickupTime || booking.scheduledTime || '').slice(0, 5)} ${routeDirectionLabel(booking.routeDirection).toLowerCase()}`;

    setDialog({
      title: hasCurrentRoute ? 'Add to current route?' : 'Carpool route available',
      message: `${fullRouteSeats} child${fullRouteSeats === 1 ? '' : 'ren'} share the ${routeLabel} pickup window. Vehicle seats: ${fullRouteSeats}/${capacity}. Include ${children.join(', ')}?`,
      actions: hasCurrentRoute
        ? [
            { label: 'Cancel', variant: 'ghost' },
            { label: 'Add Route', icon: 'plus', variant: 'primary', onPress: () => claimRoute(booking, { includeCarpool: true }) },
          ]
        : [
            { label: routeChildCount > 1 ? 'Claim Plan Route' : 'Only This', icon: 'check', variant: 'ghost', onPress: () => claimRoute(booking) },
            { label: 'Claim Full Carpool', icon: 'users', variant: 'primary', onPress: () => claimRoute(booking, { includeCarpool: true }) },
            { label: 'Cancel', variant: 'ghost' },
          ],
    });
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active={isParent ? 'plans' : 'bookings'} />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow={isDriver ? 'Route Bounties' : 'Monthly Plans'}
        title={isDriver ? 'Claim today\'s child route jobs.' : 'Book monthly plans and review daily logs.'}
        subtitle={isDriver ? 'Only current-date routes are shown so future and past jobs stay out of today\'s board.' : 'Daily trip logs are grouped as simple dated entries. Tap a date to view the route details.'}
      />

      <SectionCard title="Active connection" subtitle={ride ? `${ride.studentName} - ${ride.vehicle}` : 'No active trip'} icon="link">
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            {!isDriver ? <DriverProfileCard ride={ride} context="active" /> : null}
            <InfoRow icon={isDriver ? 'user-friends' : 'car'} label={isDriver ? 'Parent' : 'Driver'} value={isDriver ? ride.parentName : ride.driverName} />
            <InfoRow icon="clock" label="Pickup time" value={ride.pickupTime || 'Pending'} />
            <InfoRow icon="stopwatch" label="ETA" value={`${ride.etaMinutes} mins`} />
            <AppButton icon={isDriver ? 'tasks' : 'map-marked-alt'} label={isDriver ? 'Open Trip Controls' : 'Track Live Trip'} onPress={() => navigation.navigate(isDriver ? 'DriverTrips' : 'ActiveRideMap')} />
            <AppButton icon={isDriver ? 'route' : 'comments'} label={isDriver ? 'Track Live Route' : 'Messages'} variant="secondary" onPress={() => navigation.navigate(isDriver ? 'ActiveRideMap' : 'Chat')} />
            {isDriver ? <AppButton icon="comment-dots" label="Messages" variant="ghost" onPress={() => navigation.navigate('Chat')} /> : null}
          </>
        ) : (
          <Text>No trip is active yet.</Text>
        )}
      </SectionCard>

      <SectionCard title={isDriver ? 'Today\'s transport board' : 'Daily transport logs'} subtitle={isDriver ? 'Claim a pending current-date route to take the job.' : 'Tap a transport date to show full route details.'} icon="calendar-check">
        {groupedBookings.map((booking) => {
          const key = bookingKey(booking);
          const isExpanded = expandedBookingKey === key;
          const isCancellable = canCancelDailyTrip(booking, isParent);
          const headerChildren = booking.childNames?.length ? booking.childNames.join(', ') : booking.studentName;
          const planRange = monthlyPlanRangeLabel(booking);

          return (
            <View key={key} style={styles.logItem}>
              {isParent ? (
                <View style={styles.parentLogHeader}>
                  <Pressable onPress={() => setExpandedBookingKey(isExpanded ? null : key)} style={({ pressed }) => [styles.logHeaderMain, pressed && styles.pressed]}>
                    <Text style={styles.logDate}>{serviceDateLabel(booking.scheduledDate)}</Text>
                    <Text style={styles.logMeta}>{headerChildren || 'Monthly plan route'} / {booking.pickupTime || booking.scheduledTime || '-'} / {String(booking.status || 'pending').replace('_', ' ')}</Text>
                    {planRange ? <Text style={styles.planRange}>Monthly plan: {planRange}</Text> : null}
                  </Pressable>
                  <View style={styles.headerActions}>
                    <Pill label={booking.status} tone={booking.status === 'assigned' || booking.status === 'completed' ? 'success' : 'warning'} />
                    {isCancellable ? (
                      <Pressable onPress={() => cancelDailyTrip(booking)} style={({ pressed }) => [styles.compactCancelButton, pressed && styles.pressed]}>
                        <FontAwesome5 name="ban" size={12} color={colors.deep} />
                        <Text style={styles.compactCancelText}>Cancel</Text>
                      </Pressable>
                    ) : null}
                    <Text style={styles.logHint}>{isExpanded ? 'Hide' : 'View'}</Text>
                  </View>
                </View>
              ) : (
                <View style={styles.driverHeader}>
                  <Text style={styles.driverTitle}>{booking.isMonthlyPlan ? `${booking.routeChildCount} child route` : booking.studentName}</Text>
                  <Text style={styles.logHint}>{booking.pickupTime || booking.scheduledTime}</Text>
                </View>
              )}

              {isDriver || isExpanded ? (
                <View style={styles.details}>
                  <Pill label={booking.status} tone={booking.status === 'assigned' || booking.status === 'completed' ? 'success' : 'warning'} />
                  <InfoRow icon="calendar-day" label="Exact service date" value={serviceDateLabel(booking.scheduledDate)} />
                  {planRange ? <InfoRow icon="calendar-alt" label="Monthly plan coverage" value={planRange} /> : null}
                  {booking.isMonthlyPlan ? <InfoRow icon="users" label="Children" value={booking.childNames.join(', ')} /> : <InfoRow icon="child" label="Child" value={booking.studentName} />}
                  <InfoRow icon="route" label="Direction" value={routeDirectionLabel(booking.routeDirection)} />
                  <InfoRow icon="clock" label="Pickup time" value={booking.pickupTime || booking.scheduledTime} />
                  <InfoRow icon="flag-checkered" label="Drop-off time" value={booking.dropoffTime || '-'} />
                  {booking.adjustedPickupTime ? <InfoRow icon="bell" label="Adjusted pickup" value={booking.adjustedPickupTime} /> : null}
                  <InfoRow icon="map-marker-alt" label="Pickup" value={booking.pickupAddress} />
                  <InfoRow icon="flag-checkered" label="Drop-off" value={booking.dropoffAddress} />
                  {!isDriver && booking.driver ? <DriverProfileCard driver={booking.driver} context="transport" /> : null}
                  <InfoRow icon={isDriver ? 'user-friends' : 'car'} label={isDriver ? 'Parent' : 'Driver'} value={isDriver ? booking.parentName || booking.driverName : booking.driverName} />
                  <InfoRow icon="exchange-alt" label="Trip type" value={booking.isMonthlyPlan ? 'monthly plan daily route' : booking.tripType ? booking.tripType.replace('_', ' ') : '-'} />
                  {isDriver ? <InfoRow icon="users" label="Vehicle seats" value={`${booking.routeSeatCount || booking.routeChildCount || 1}/${booking.vehicleCapacity || vehicleCapacity}`} /> : null}
                  {booking.totalPayoutAmount ? <InfoRow icon="coins" label="Route payout" value={`PHP ${booking.totalPayoutAmount.toFixed(2)}`} /> : null}
                  {isCancellable ? (
                    <AppButton icon="ban" label="No Class / Student Absent" variant="ghost" onPress={() => cancelDailyTrip(booking)} />
                  ) : null}
                  {isDriver && booking.canApprove ? (
                    <>
                      <AppButton icon="check" label={`Claim ${booking.pickupTime || booking.scheduledTime} Route`} onPress={() => handleClaim(booking)} />
                      <AppButton icon="times" label="Skip Route" variant="ghost" onPress={() => skipRoute(booking)} />
                    </>
                  ) : null}
                  {isDriver && !booking.canApprove && !booking.rideId ? <InfoRow icon="ban" label="Capacity" value={booking.capacityMessage || 'Vehicle capacity is full.'} /> : null}
                  {isDriver && booking.rideId && transferOptions.length ? (
                    <>
                      <DropdownField label="Nearby emergency driver" value={transferDriverId} options={transferOptions} placeholder="Select nearby driver" onChange={setTransferDriverId} />
                      <FormInput label="Handoff note" value={transferHandoffNote} placeholder="Car trouble, pickup delay, meeting point, or safety instruction" multiline onChangeText={setTransferHandoffNote} />
                      <AppButton icon="exchange-alt" label="Transfer Route and Alert Parents" variant="secondary" onPress={() => handleTransfer(booking)} />
                    </>
                  ) : null}
                </View>
              ) : null}
            </View>
          );
        })}
        {!groupedBookings.length ? <Text>{isDriver ? 'No current-date routes are available right now.' : 'No daily transport logs yet.'}</Text> : null}
        {isParent ? <AppButton icon="plus" label="Book Monthly Plan" onPress={() => navigation.navigate('BookRide')} /> : null}
      </SectionCard>

      <SectionCard title="Recent coordination" subtitle="Latest TRACE messages between the parent and driver." icon="comments">
        {recentMessages.map((message) => (
          <SectionCard key={message.id} title={message.senderName} subtitle={message.time}>
            {imageUrlForMessage(message) ? (
              <View style={styles.recentImageWrap}>
                <Image source={{ uri: imageUrlForMessage(message) }} style={styles.recentImage} resizeMode="cover" />
                <Text style={styles.recentImageText}>{message.text || 'Drop-off photo'}</Text>
              </View>
            ) : (
              <Text>{message.text}</Text>
            )}
          </SectionCard>
        ))}
        <AppButton icon="comment-dots" label="Open Conversation" variant="ghost" onPress={() => navigation.navigate('Chat')} />
      </SectionCard>
      <AppDialog
        visible={Boolean(dialog)}
        title={dialog?.title}
        message={dialog?.message}
        actions={dialog?.actions}
        onClose={() => setDialog(null)}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  logItem: {
    borderBottomColor: colors.line,
    borderBottomWidth: 1,
    paddingVertical: 10,
  },
  parentLogHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
    minHeight: 44,
  },
  logHeaderMain: {
    flex: 1,
    gap: 3,
    minWidth: 0,
  },
  headerActions: {
    alignItems: 'flex-end',
    gap: 6,
  },
  compactCancelButton: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 10,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 6,
    minHeight: 34,
    paddingHorizontal: 10,
  },
  compactCancelText: {
    color: colors.deep,
    fontSize: 12,
    fontWeight: '800',
  },
  driverHeader: {
    gap: 4,
    minHeight: 44,
  },
  driverTitle: {
    fontSize: 16,
    fontWeight: '800',
  },
  recentImageWrap: {
    gap: 8,
  },
  recentImage: {
    backgroundColor: colors.line,
    borderRadius: 12,
    height: 180,
    width: '100%',
  },
  recentImageText: {
    color: colors.slate,
    fontSize: 12,
    fontWeight: '700',
  },
  logDate: {
    fontSize: 16,
    fontWeight: '800',
  },
  logMeta: {
    color: colors.slate,
    fontSize: 12,
    fontWeight: '700',
  },
  planRange: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '700',
  },
  logHint: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.accent,
  },
  details: {
    paddingTop: 10,
  },
  pressed: {
    opacity: 0.8,
  },
});
