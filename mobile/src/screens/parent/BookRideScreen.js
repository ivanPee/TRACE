import React, { useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function BookRideScreen({ navigation }) {
  const { students, createBooking } = useAppContext();
  const firstStudent = students[0];
  const [form, setForm] = useState({
    studentId: firstStudent?.id || '',
    studentName: firstStudent?.name || '',
    pickupAddress: firstStudent?.pickupAddress || '',
    dropoffAddress: firstStudent?.dropoffAddress || '',
    scheduledDate: '2026-05-03',
    scheduledTime: '07:00 AM',
    tripType: 'Round Trip',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = () => {
    createBooking(form);
    navigation.goBack();
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />}>
      <HeaderBlock
        eyebrow="Ride Booking"
        title="Create a student transport booking."
        subtitle="This version uses the first linked student by default, which keeps the prototype flow simple while still matching your business rules."
      />
      <SectionCard>
        <Text>Selected Student: {firstStudent?.name || 'No student yet'}</Text>
        <FormInput label="Pickup Address" value={form.pickupAddress} onChangeText={(value) => updateField('pickupAddress', value)} placeholder="Pickup address" multiline />
        <FormInput label="Drop-off Address" value={form.dropoffAddress} onChangeText={(value) => updateField('dropoffAddress', value)} placeholder="Drop-off address" multiline />
        <FormInput label="Scheduled Date" value={form.scheduledDate} onChangeText={(value) => updateField('scheduledDate', value)} placeholder="YYYY-MM-DD" />
        <FormInput label="Scheduled Time" value={form.scheduledTime} onChangeText={(value) => updateField('scheduledTime', value)} placeholder="07:00 AM" />
        <FormInput label="Trip Type" value={form.tripType} onChangeText={(value) => updateField('tripType', value)} placeholder="One Way / Round Trip / Recurring" />
        <AppButton label="Submit Ride Booking" onPress={handleSubmit} />
      </SectionCard>
    </Screen>
  );
}
