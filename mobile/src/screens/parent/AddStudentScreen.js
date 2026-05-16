import React, { useState } from 'react';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function AddStudentScreen({ navigation }) {
  const { addStudent, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [form, setForm] = useState({
    studentName: '',
    lrn: '',
    schoolName: '',
    gradeLevel: '',
    pickupAddress: '',
    pickupLatitude: '10.676',
    pickupLongitude: '122.562',
    dropoffAddress: '',
    dropoffLatitude: '10.676',
    dropoffLongitude: '122.562',
    emergencyContact: '',
    notes: '',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const updatePinnedAddress = (prefix, { address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      [`${prefix}Address`]: address,
      [`${prefix}Latitude`]: latitude,
      [`${prefix}Longitude`]: longitude,
    }));
  };

  const handleSave = async () => {
    if (!form.studentName || !form.lrn) {
      return;
    }

    await addStudent({
      studentName: form.studentName,
      lrn: form.lrn,
      schoolName: form.schoolName,
      gradeLevel: form.gradeLevel,
      pickupAddress: form.pickupAddress,
      pickupLatitude: form.pickupLatitude,
      pickupLongitude: form.pickupLongitude,
      dropoffAddress: form.dropoffAddress,
      dropoffLatitude: form.dropoffLatitude,
      dropoffLongitude: form.dropoffLongitude,
      emergencyContact: form.emergencyContact,
      notes: form.notes,
    });

    navigation.goBack();
  };
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="New Student"
        title="Create the student account from the parent side."
        subtitle="In the real backend this should validate that the LRN is unique before saving."
      />
      <SectionCard icon="user-plus">
        <FormInput label="Student Name" value={form.studentName} onChangeText={(value) => updateField('studentName', value)} placeholder="Lia Villanueva" />
        <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="112233445566" />
        <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School name" />
        <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade 8" />
        <AddressPinPicker label="Pickup Address" value={form.pickupAddress} latitude={form.pickupLatitude} longitude={form.pickupLongitude} onChange={(value) => updatePinnedAddress('pickup', value)} />
        <AddressPinPicker label="Drop-off Address" value={form.dropoffAddress} latitude={form.dropoffLatitude} longitude={form.dropoffLongitude} onChange={(value) => updatePinnedAddress('dropoff', value)} />
        <FormInput label="Emergency Contact" value={form.emergencyContact} onChangeText={(value) => updateField('emergencyContact', value)} placeholder="Parent mobile number" />
        <FormInput label="Medical Notes" value={form.notes} onChangeText={(value) => updateField('notes', value)} placeholder="Optional notes" multiline />
        <AppButton icon="save" label="Save Student Account" onPress={handleSave} />
      </SectionCard>
    </Screen>
  );
}
