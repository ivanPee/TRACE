import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Text } from 'react-native';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DropdownField from '../../components/DropdownField';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

const defaultBookingForm = (student, driver, booking) => ({
  studentId: booking?.studentId || student?.id || '',
  studentName: booking?.studentName || student?.name || '',
  driverId: booking?.driverId || driver?.id || '',
  pickupAddress: booking?.pickupAddress || student?.pickupAddress || '',
  dropoffAddress: booking?.dropoffAddress || student?.dropoffAddress || '',
  pickupLatitude: booking?.pickupLatitude || student?.pickupLatitude || '10.676',
  pickupLongitude: booking?.pickupLongitude || student?.pickupLongitude || '122.562',
  dropoffLatitude: booking?.dropoffLatitude || student?.dropoffLatitude || '10.676',
  dropoffLongitude: booking?.dropoffLongitude || student?.dropoffLongitude || '122.562',
  scheduledDate: booking?.scheduledDate || '',
  scheduledTime: booking?.scheduledTime || '',
  tripType: booking?.tripType || 'one_way',
});

export default function BookRideScreen({ navigation, route }) {
  const { students, availableDrivers, createBooking, updateBooking, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const firstStudent = students[0];
  const firstDriver = availableDrivers[0];
  const editingBooking = route?.params?.booking;
  const initialForm = useMemo(() => defaultBookingForm(firstStudent, firstDriver, editingBooking), [editingBooking, firstDriver, firstStudent]);
  const [form, setForm] = useState(initialForm);
  const initialSnapshot = useRef(JSON.stringify(initialForm));
  const bypassLeaveConfirm = useRef(false);
  const isEditing = Boolean(editingBooking?.id);
  const studentOptions = useMemo(() => students.map((student) => ({ label: student.name, value: student.id })), [students]);
  const driverOptions = useMemo(
    () => availableDrivers.map((driver) => ({ label: `${driver.name}${driver.isOnline ? ' - Online' : ' - Offline'} (${driver.vehicle})`, value: driver.id })),
    [availableDrivers]
  );
  const tripTypeOptions = [
    { label: 'One way', value: 'one_way' },
    { label: 'Round trip', value: 'round_trip' },
    { label: 'Recurring', value: 'recurring' },
  ];
  const isComplete = Boolean(
    form.studentId &&
      form.driverId &&
      form.pickupAddress.trim() &&
      form.dropoffAddress.trim() &&
      form.pickupLatitude &&
      form.pickupLongitude &&
      form.dropoffLatitude &&
      form.dropoffLongitude &&
      form.scheduledDate.trim() &&
      form.scheduledTime.trim()
  );

  useEffect(() => {
    setForm(initialForm);
    initialSnapshot.current = JSON.stringify(initialForm);
    bypassLeaveConfirm.current = false;
  }, [initialForm]);

  useEffect(() => {
    const unsubscribe = navigation.addListener('beforeRemove', (event) => {
      const hasChanges = JSON.stringify(form) !== initialSnapshot.current;

      if (bypassLeaveConfirm.current || !hasChanges) {
        return;
      }

      event.preventDefault();
      Alert.alert('Discard booking changes?', 'Your booking details have unsaved changes.', [
        { text: 'Keep Editing', style: 'cancel' },
        {
          text: 'Discard',
          style: 'destructive',
          onPress: () => {
            bypassLeaveConfirm.current = true;
            navigation.dispatch(event.data.action);
          },
        },
      ]);
    });

    return unsubscribe;
  }, [form, navigation]);

  useEffect(() => {
    if (!isEditing && !form.studentId && firstStudent) {
      setForm((current) => ({
        ...current,
        studentId: firstStudent.id,
        studentName: firstStudent.name,
        pickupAddress: firstStudent.pickupAddress || current.pickupAddress,
        dropoffAddress: firstStudent.dropoffAddress || current.dropoffAddress,
        pickupLatitude: firstStudent.pickupLatitude || current.pickupLatitude,
        pickupLongitude: firstStudent.pickupLongitude || current.pickupLongitude,
        dropoffLatitude: firstStudent.dropoffLatitude || current.dropoffLatitude,
        dropoffLongitude: firstStudent.dropoffLongitude || current.dropoffLongitude,
      }));
    }

    if (!isEditing && !form.driverId && firstDriver) {
      setForm((current) => ({ ...current, driverId: firstDriver.id }));
    }
  }, [firstDriver, firstStudent, form.driverId, form.studentId, isEditing]);

  const updateField = (key, value) => {
    setForm((current) => {
      if (key === 'studentId') {
        const student = students.find((item) => String(item.id) === String(value));

        return {
          ...current,
          studentId: value,
          studentName: student?.name || '',
          pickupAddress: student?.pickupAddress || current.pickupAddress,
          dropoffAddress: student?.dropoffAddress || current.dropoffAddress,
          pickupLatitude: student?.pickupLatitude || current.pickupLatitude,
          pickupLongitude: student?.pickupLongitude || current.pickupLongitude,
          dropoffLatitude: student?.dropoffLatitude || current.dropoffLatitude,
          dropoffLongitude: student?.dropoffLongitude || current.dropoffLongitude,
        };
      }

      return { ...current, [key]: value };
    });
  };
  const updatePinnedAddress = (prefix, { address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      [`${prefix}Address`]: address,
      [`${prefix}Latitude`]: latitude,
      [`${prefix}Longitude`]: longitude,
    }));
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  const handleSubmit = async () => {
    if (!isComplete) {
      Alert.alert('Complete booking details', 'Please select a student, driver, pickup and drop-off pins, schedule date, and schedule time before submitting.');
      return;
    }

    setSaving(true);
    try {
      const payload = {
        ...form,
        studentId: form.studentId || firstStudent?.id,
        driverId: form.driverId || firstDriver?.id,
      };

      if (isEditing) {
        await updateBooking(editingBooking.id, payload);
      } else {
        await createBooking(payload);
      }

      bypassLeaveConfirm.current = true;
      navigation.goBack();
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow={isEditing ? 'Edit Booking' : 'Ride Booking'}
        title={isEditing ? 'Update the trip details.' : 'Create a student transport booking.'}
        subtitle="Select an approved driver, pin both addresses, and set the schedule before submitting."
      />
      <SectionCard icon="calendar-plus">
        <Text>Driver approval is required before the ride becomes active.</Text>
        <DropdownField label="Student" value={form.studentId} options={studentOptions} placeholder="Select student" onChange={(value) => updateField('studentId', value)} />
        <DropdownField label="Driver" value={form.driverId} options={driverOptions} placeholder="Select approved driver" onChange={(value) => updateField('driverId', value)} />
        <AddressPinPicker label="Pickup Address" value={form.pickupAddress} latitude={form.pickupLatitude} longitude={form.pickupLongitude} onChange={(value) => updatePinnedAddress('pickup', value)} />
        <AddressPinPicker label="Drop-off Address" value={form.dropoffAddress} latitude={form.dropoffLatitude} longitude={form.dropoffLongitude} onChange={(value) => updatePinnedAddress('dropoff', value)} />
        <FormInput label="Scheduled Date" value={form.scheduledDate} onChangeText={(value) => updateField('scheduledDate', value)} placeholder="YYYY-MM-DD" />
        <FormInput label="Scheduled Time" value={form.scheduledTime} onChangeText={(value) => updateField('scheduledTime', value)} placeholder="07:00 AM" />
        <DropdownField label="Trip Type" value={form.tripType} options={tripTypeOptions} onChange={(value) => updateField('tripType', value)} />
        <AppButton icon={isEditing ? 'save' : 'paper-plane'} label={saving ? 'Saving...' : isEditing ? 'Update Booking' : 'Submit Ride Booking'} disabled={saving || !isComplete} onPress={handleSubmit} />
      </SectionCard>
    </Screen>
  );
}
