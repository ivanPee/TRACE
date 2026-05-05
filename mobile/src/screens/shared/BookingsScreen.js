import React from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function BookingsScreen({ navigation }) {
  const { currentRole, bookings, rides, messages } = useAppContext();
  const ride = rides[0];
  const recentMessages = messages.slice(-2);
  const isDriver = currentRole === 'driver';
  const isParent = currentRole === 'parent';

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />}>
      <HeaderBlock
        eyebrow={isDriver ? 'Pickup Manager' : 'Booking Manager'}
        title={isDriver ? 'Handle pickups and parent coordination.' : 'Manage bookings and driver coordination.'}
        subtitle="This tab groups the active trip, booking queue, and parent-driver communication into one workflow."
      />

      <SectionCard title="Active connection" subtitle={ride ? `${ride.studentName} - ${ride.vehicle}` : 'No active ride'}>
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <InfoRow label={isDriver ? 'Parent' : 'Driver'} value={isDriver ? ride.parentName : ride.driverName} />
            <InfoRow label="Pickup time" value={ride.pickupTime} />
            <InfoRow label="ETA" value={`${ride.etaMinutes} mins`} />
            <AppButton label={isDriver ? 'Open Trip Controls' : 'Track Live Ride'} onPress={() => navigation.navigate(isDriver ? 'DriverTrips' : 'ActiveRideMap')} />
            <AppButton label={isDriver ? 'Track Live Route' : 'Message Driver'} variant="secondary" onPress={() => navigation.navigate(isDriver ? 'ActiveRideMap' : 'Chat')} />
            {isDriver ? <AppButton label="Message Parent" variant="ghost" onPress={() => navigation.navigate('Chat')} /> : null}
          </>
        ) : (
          <Text>No ride is active yet.</Text>
        )}
      </SectionCard>

      <SectionCard title={isDriver ? 'Assigned bookings' : 'Submitted bookings'} subtitle="Current booking requests and ride schedules.">
        {bookings.map((booking) => (
          <SectionCard key={booking.id} title={booking.studentName} subtitle={`${booking.scheduledDate} - ${booking.scheduledTime}`}>
            <Pill label={booking.status} tone={booking.status === 'assigned' ? 'success' : 'warning'} />
            <InfoRow label="Pickup" value={booking.pickupAddress} />
            <InfoRow label="Drop-off" value={booking.dropoffAddress} />
            <InfoRow label={isDriver ? 'Assigned by' : 'Driver'} value={booking.driverName} />
          </SectionCard>
        ))}
        {isParent ? <AppButton label="Create New Booking" onPress={() => navigation.navigate('BookRide')} /> : null}
      </SectionCard>

      <SectionCard title="Recent coordination" subtitle="Latest TRACE messages between the parent and driver.">
        {recentMessages.map((message) => (
          <SectionCard key={message.id} title={message.senderName} subtitle={message.time}>
            <Text>{message.text}</Text>
          </SectionCard>
        ))}
        <AppButton label="Open Conversation" variant="ghost" onPress={() => navigation.navigate('Chat')} />
      </SectionCard>
    </Screen>
  );
}
