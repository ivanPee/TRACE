import React, { useState } from 'react';
import { Alert, Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DropdownField from '../../components/DropdownField';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function BookingsScreen({ navigation }) {
  const { currentRole, currentUser, availableDrivers, bookings, rides, messages, refreshDashboard, approveBooking, rejectBooking, transferRide } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [transferDriverId, setTransferDriverId] = useState('');
  const ride = rides[0];
  const recentMessages = messages.slice(-2);
  const isDriver = currentRole === 'driver';
  const isParent = currentRole === 'parent';
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
    .map((driver) => ({ label: `${driver.name} - ${driver.vehicle}${driver.isOnline ? ' (Online)' : ''}`, value: driver.id }));
  const handleTransfer = async (booking) => {
    if (!booking.rideId || !transferDriverId) {
      Alert.alert('Transfer booking', 'Select another approved driver first.');
      return;
    }

    await transferRide(booking.rideId, transferDriverId);
    setTransferDriverId('');
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow={isDriver ? 'Pickup Manager' : 'Booking Manager'}
        title={isDriver ? 'Handle pickups and parent coordination.' : 'Manage bookings and driver coordination.'}
        subtitle="This tab groups the active trip, booking queue, and parent-driver communication into one workflow."
      />

      <SectionCard title="Active connection" subtitle={ride ? `${ride.studentName} - ${ride.vehicle}` : 'No active ride'} icon="link">
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <InfoRow icon={isDriver ? 'user-friends' : 'car'} label={isDriver ? 'Parent' : 'Driver'} value={isDriver ? ride.parentName : ride.driverName} />
            <InfoRow icon="clock" label="Pickup time" value={ride.pickupTime || 'Pending'} />
            <InfoRow icon="stopwatch" label="ETA" value={`${ride.etaMinutes} mins`} />
            <AppButton icon={isDriver ? 'tasks' : 'map-marked-alt'} label={isDriver ? 'Open Trip Controls' : 'Track Live Ride'} onPress={() => navigation.navigate(isDriver ? 'DriverTrips' : 'ActiveRideMap')} />
            <AppButton icon={isDriver ? 'route' : 'comments'} label={isDriver ? 'Track Live Route' : 'Message Driver'} variant="secondary" onPress={() => navigation.navigate(isDriver ? 'ActiveRideMap' : 'Chat')} />
            {isDriver ? <AppButton icon="comment-dots" label="Message Parent" variant="ghost" onPress={() => navigation.navigate('Chat')} /> : null}
          </>
        ) : (
          <Text>No ride is active yet.</Text>
        )}
      </SectionCard>

      <SectionCard title={isDriver ? 'Assigned bookings' : 'Submitted bookings'} subtitle="Current booking requests and ride schedules." icon="calendar-check">
        {bookings.map((booking) => (
          <SectionCard key={booking.id} title={booking.studentName} subtitle={`${booking.scheduledDate} - ${booking.scheduledTime}`} icon="child">
            <Pill label={booking.status} tone={booking.status === 'assigned' || booking.status === 'completed' ? 'success' : 'warning'} />
            <InfoRow icon="map-marker-alt" label="Pickup" value={booking.pickupAddress} />
            <InfoRow icon="flag-checkered" label="Drop-off" value={booking.dropoffAddress} />
            <InfoRow icon={isDriver ? 'user-friends' : 'car'} label={isDriver ? 'Parent' : 'Driver'} value={isDriver ? booking.parentName || booking.driverName : booking.driverName} />
            <InfoRow icon="exchange-alt" label="Trip type" value={booking.tripType ? booking.tripType.replace('_', ' ') : '-'} />
            {isDriver && booking.canApprove ? (
              <>
                <AppButton icon="check" label="Approve Booking" onPress={() => approveBooking(booking.id)} />
                <AppButton icon="times" label="Reject Booking" variant="ghost" onPress={() => rejectBooking(booking.id)} />
              </>
            ) : null}
            {isDriver && booking.rideId && transferOptions.length ? (
              <>
                <DropdownField label="Transfer to driver" value={transferDriverId} options={transferOptions} placeholder="Select another driver" onChange={setTransferDriverId} />
                <AppButton icon="exchange-alt" label="Transfer Booking" variant="secondary" onPress={() => handleTransfer(booking)} />
              </>
            ) : null}
            {isParent && !['completed', 'cancelled'].includes(String(booking.status).toLowerCase()) ? (
              <AppButton icon="edit" label="Edit Booking Details" variant="secondary" onPress={() => navigation.navigate('BookRide', { booking })} />
            ) : null}
          </SectionCard>
        ))}
        {isParent ? <AppButton icon="plus" label="Create New Booking" onPress={() => navigation.navigate('BookRide')} /> : null}
      </SectionCard>

      <SectionCard title="Recent coordination" subtitle="Latest TRACE messages between the parent and driver." icon="comments">
        {recentMessages.map((message) => (
          <SectionCard key={message.id} title={message.senderName} subtitle={message.time}>
            <Text>{message.text}</Text>
          </SectionCard>
        ))}
        <AppButton icon="comment-dots" label="Open Conversation" variant="ghost" onPress={() => navigation.navigate('Chat')} />
      </SectionCard>
    </Screen>
  );
}
