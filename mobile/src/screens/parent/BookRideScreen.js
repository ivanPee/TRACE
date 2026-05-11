import React, { useEffect, useMemo, useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DropdownField from '../../components/DropdownField';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function BookRideScreen({ navigation }) {
  const { students, availableDrivers, createBooking, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const firstStudent = students[0];
  const firstDriver = availableDrivers[0];
  const [form, setForm] = useState({
    studentId: firstStudent?.id || '',
    studentName: firstStudent?.name || '',
    driverId: firstDriver?.id || '',
    pickupAddress: firstStudent?.pickupAddress || '',
    dropoffAddress: firstStudent?.dropoffAddress || '',
    scheduledDate: '',
    scheduledTime: '',
    tripType: 'one_way',
  });
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

  useEffect(() => {
    if (!form.studentId && firstStudent) {
      setForm((current) => ({
        ...current,
        studentId: firstStudent.id,
        studentName: firstStudent.name,
        pickupAddress: firstStudent.pickupAddress || current.pickupAddress,
        dropoffAddress: firstStudent.dropoffAddress || current.dropoffAddress,
      }));
    }

    if (!form.driverId && firstDriver) {
      setForm((current) => ({ ...current, driverId: firstDriver.id }));
    }
  }, [firstDriver, firstStudent, form.driverId, form.studentId]);

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
        };
      }

      return { ...current, [key]: value };
    });
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
    await createBooking({
      ...form,
      studentId: form.studentId || firstStudent?.id,
      driverId: form.driverId || firstDriver?.id,
    });
    navigation.goBack();
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Ride Booking"
        title="Create a student transport booking."
        subtitle="Select an approved driver, then the system links parent, student, and driver."
      />
      <SectionCard>
        <Text>Driver approval is required before the ride becomes active.</Text>
        <DropdownField label="Student" value={form.studentId} options={studentOptions} placeholder="Select student" onChange={(value) => updateField('studentId', value)} />
        <DropdownField label="Driver" value={form.driverId} options={driverOptions} placeholder="Select approved driver" onChange={(value) => updateField('driverId', value)} />
        <FormInput label="Pickup Address" value={form.pickupAddress} onChangeText={(value) => updateField('pickupAddress', value)} placeholder="Pickup address" multiline />
        <FormInput label="Drop-off Address" value={form.dropoffAddress} onChangeText={(value) => updateField('dropoffAddress', value)} placeholder="Drop-off address" multiline />
        <FormInput label="Scheduled Date" value={form.scheduledDate} onChangeText={(value) => updateField('scheduledDate', value)} placeholder="YYYY-MM-DD" />
        <FormInput label="Scheduled Time" value={form.scheduledTime} onChangeText={(value) => updateField('scheduledTime', value)} placeholder="07:00 AM" />
        <DropdownField label="Trip Type" value={form.tripType} options={tripTypeOptions} onChange={(value) => updateField('tripType', value)} />
        <AppButton label="Submit Ride Booking" onPress={handleSubmit} />
      </SectionCard>
    </Screen>
  );
}
